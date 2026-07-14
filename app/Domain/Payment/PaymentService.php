<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Audit\AuditLogger;
use App\Domain\Order\OrderLifecycleService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single entry point for everything that mutates Payment / PaymentTransaction.
 *
 * - initiateFor() — creates the Payment row and asks the provider to initiate
 * - capture() — captures an authorised payment (sync providers no-op succeeds)
 * - refund() — issues a refund (full or partial)
 *
 * Whenever a payment reaches captured status, this service tells the
 * OrderLifecycleService to mark the order paid. That way payment-driven
 * state changes go through the same audited path as admin-driven ones.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly OrderLifecycleService $orders,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create a Payment for an Order and ask the provider to initiate it.
     * Returns the PaymentResult so the controller can decide whether to
     * redirect (PSP flows) or render a thank-you page (COD / sync flows).
     */
    public function initiateFor(Order $order, PaymentMethod $method): PaymentResult
    {
        if ($order->payment_status === Order::PAY_PAID) {
            throw new RuntimeException('This order has already been paid.');
        }

        return DB::transaction(function () use ($order, $method) {
            $payment = Payment::create([
                'order_id'          => $order->id,
                'payment_method_id' => $method->id,
                'method_slug'       => $method->slug,
                'provider'          => $method->provider,
                'status'            => Payment::STATUS_PENDING,
                'amount_minor'      => $order->total_minor,
                'currency'          => $order->currency,
            ]);

            $provider = $this->providers->resolve($method->provider);
            $result = $provider->initiate($order, $payment);

            $this->applyResult($payment, PaymentTransaction::TYPE_AUTHORIZE, $result);

            // If the provider captured synchronously (COD pending / online_mock captured),
            // sync the order. COD stays pending — order moves to paid only after admin
            // marks it captured upon delivery.
            if ($result->succeeded && $result->newStatus === 'captured') {
                $this->orders->markPaid($order);
            }

            return $result;
        });
    }

    /**
     * Capture a previously-authorised payment (used by COD-on-delivery,
     * manual_transfer-after-confirmed-receipt, or by online providers that
     * require explicit capture after authorisation).
     */
    public function capture(Payment $payment, ?int $amountMinor = null): PaymentResult
    {
        if ($payment->status === Payment::STATUS_CAPTURED) {
            throw new RuntimeException('Payment already captured.');
        }

        return DB::transaction(function () use ($payment, $amountMinor) {
            $provider = $this->providers->resolve($payment->provider);
            $result = $provider->capture($payment, $amountMinor);

            $this->applyResult($payment, PaymentTransaction::TYPE_CAPTURE, $result);

            if ($result->succeeded && $result->newStatus === 'captured') {
                $this->orders->markPaid($payment->order);
            }

            return $result;
        });
    }

    public function refund(Payment $payment, ?int $amountMinor = null, ?string $reason = null): PaymentResult
    {
        if (! in_array($payment->status, [Payment::STATUS_CAPTURED, Payment::STATUS_PARTIAL_REFUND], true)) {
            throw new RuntimeException('Only captured (or partially-refunded) payments can be refunded.');
        }
        $refundable = $payment->amount_minor - $payment->refunded_minor;
        $request = $amountMinor ?? $refundable;
        if ($request <= 0 || $request > $refundable) {
            throw new RuntimeException("Invalid refund amount. Max refundable: {$refundable}.");
        }

        return DB::transaction(function () use ($payment, $request, $reason) {
            $provider = $this->providers->resolve($payment->provider);
            $result = $provider->refund($payment, $request, $reason);

            $this->applyResult($payment, PaymentTransaction::TYPE_REFUND, $result);

            if ($result->succeeded) {
                $totalRefunded = $payment->refunded_minor + $request;
                $payment->update([
                    'refunded_minor' => $totalRefunded,
                    'status' => $totalRefunded >= $payment->amount_minor
                        ? Payment::STATUS_REFUNDED
                        : Payment::STATUS_PARTIAL_REFUND,
                    'refunded_at' => now(),
                ]);

                // Reflect on order
                $order = $payment->order;
                $order->update([
                    'payment_status' => $totalRefunded >= $payment->amount_minor
                        ? Order::PAY_REFUNDED
                        : Order::PAY_PARTIAL_REFUND,
                ]);

                $this->audit->log('payment.refunded', $payment, after: [
                    'amount_refunded' => $request,
                    'reason'          => $reason,
                ]);
            }

            return $result;
        });
    }

    /**
     * Write the result of a provider operation onto the Payment row and
     * record a PaymentTransaction for the audit trail. Single source of
     * truth for "what happened to this payment" — every provider call goes
     * through here.
     */
    private function applyResult(Payment $payment, string $txnType, PaymentResult $result): void
    {
        // Append-only transaction record
        PaymentTransaction::create([
            'payment_id'  => $payment->id,
            'type'        => $txnType,
            'status'      => $result->succeeded ? 'succeeded' : 'failed',
            'amount_minor'=> $result->amountMinor ?? 0,
            'currency'    => $payment->currency,
            'external_id' => $result->externalId,
            'payload'     => $result->payload,
            'error'       => $result->errorMessage,
        ]);

        if ($result->succeeded) {
            $update = [
                'status'      => match ($result->newStatus) {
                    'captured'   => Payment::STATUS_CAPTURED,
                    'authorized' => Payment::STATUS_AUTHORIZED,
                    'pending'    => Payment::STATUS_PENDING,
                    'refunded'   => $payment->status, // refund() handles its own status logic
                    default      => Payment::STATUS_PENDING,
                },
                'external_id' => $result->externalId ?? $payment->external_id,
                'reference'   => $result->reference ?? $payment->reference,
                'metadata'    => array_merge($payment->metadata ?? [], $result->payload),
            ];

            if ($result->newStatus === 'captured') {
                $update['captured_at'] = now();
            }
            if ($result->newStatus === 'authorized') {
                $update['authorized_at'] = now();
            }

            $payment->update($update);
        } else {
            $payment->update([
                'status'         => Payment::STATUS_FAILED,
                'failure_reason' => $result->errorMessage,
                'failed_at'      => now(),
            ]);
        }
    }
}
