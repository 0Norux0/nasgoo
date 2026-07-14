<?php

declare(strict_types=1);

namespace App\Domain\Payment\Providers;

use App\Domain\Payment\PaymentResult;
use App\Models\Order;
use App\Models\Payment;

/**
 * Manual bank transfer — customer wires the funds, admin verifies receipt
 * in their bank statement, then marks the payment captured.
 *
 * Initiate just records reference details and tells the customer where to
 * send the money; capture happens manually via the admin Filament action.
 */
class ManualBankTransferProvider implements PaymentProvider
{
    public static function name(): string { return 'manual_transfer'; }

    public function initiate(Order $order, Payment $payment, array $context = []): PaymentResult
    {
        $reference = 'BT-' . $order->number . '-' . substr(md5((string) $order->id), 0, 6);

        return PaymentResult::pending(
            amountMinor: $payment->amount_minor,
            reference: strtoupper($reference),
            payload: [
                'instructions' => 'Transfer the order total to the platform bank account. Quote the reference below in the transfer description.',
                'reference'    => strtoupper($reference),
            ],
        );
    }

    public function capture(Payment $payment, ?int $amountMinor = null): PaymentResult
    {
        return PaymentResult::captured(
            amountMinor: $amountMinor ?? $payment->amount_minor,
            payload: ['note' => 'Admin confirmed receipt of the bank transfer.'],
        );
    }

    public function refund(Payment $payment, ?int $amountMinor = null, ?string $reason = null): PaymentResult
    {
        return PaymentResult::refunded(
            amountMinor: $amountMinor ?? $payment->amount_minor,
            payload: ['reason' => $reason, 'note' => 'Manual bank refund — arrange transfer to customer.'],
        );
    }

    public function handleWebhook(string $rawBody, array $headers): PaymentResult
    {
        return PaymentResult::failed('Manual bank transfer has no webhooks.');
    }
}
