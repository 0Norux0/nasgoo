<?php

declare(strict_types=1);

namespace App\Domain\Payment\Providers;

use App\Domain\Payment\PaymentResult;
use App\Models\Order;
use App\Models\Payment;

/**
 * Cash on Delivery — no external gateway, no money moves at order time.
 *
 * Payment stays in 'pending' until either:
 *   - admin/vendor marks it captured upon successful delivery, OR
 *   - admin marks it failed on delivery failure / customer refusal.
 *
 * The order itself moves through fulfillment normally — COD vendors take
 * the delivery risk.
 */
class CashOnDeliveryProvider implements PaymentProvider
{
    public static function name(): string { return 'cod'; }

    public function initiate(Order $order, Payment $payment, array $context = []): PaymentResult
    {
        return PaymentResult::pending(
            amountMinor: $payment->amount_minor,
            reference: 'COD-' . $order->number,
            payload: ['note' => 'Cash collected on delivery — no online charge.'],
        );
    }

    public function capture(Payment $payment, ?int $amountMinor = null): PaymentResult
    {
        return PaymentResult::captured(
            amountMinor: $amountMinor ?? $payment->amount_minor,
            payload: ['note' => 'Marked paid manually (cash collected).'],
        );
    }

    public function refund(Payment $payment, ?int $amountMinor = null, ?string $reason = null): PaymentResult
    {
        // For COD, refunds are tracked but not money-moving — the platform/vendor
        // arranges the cash return outside the system.
        return PaymentResult::refunded(
            amountMinor: $amountMinor ?? $payment->amount_minor,
            payload: ['reason' => $reason, 'note' => 'COD refund — return cash to customer offline.'],
        );
    }

    public function handleWebhook(string $rawBody, array $headers): PaymentResult
    {
        return PaymentResult::failed('Cash on Delivery has no webhooks.');
    }
}
