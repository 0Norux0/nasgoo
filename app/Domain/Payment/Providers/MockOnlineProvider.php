<?php

declare(strict_types=1);

namespace App\Domain\Payment\Providers;

use App\Domain\Payment\PaymentResult;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Mock online gateway — simulates a redirect-and-return PSP without touching
 * a real network. Useful for end-to-end exercise of the redirect-checkout
 * code path in dev / CI. The mock "succeeds" by default; toggle via
 * payment_methods.config = { "force_outcome": "fail" } to exercise failure.
 *
 * Real MyFatoorah / Tap / Stripe / KNet integrations replace this class.
 */
class MockOnlineProvider implements PaymentProvider
{
    public static function name(): string { return 'online_mock'; }

    public function initiate(Order $order, Payment $payment, array $context = []): PaymentResult
    {
        // We don't actually redirect anywhere; the front-end checkout treats
        // the order as authorised+captured immediately. In a real provider,
        // initiate() would return PaymentResult::redirect($url, …).
        $external = 'MOCK-' . strtoupper(Str::random(10));

        return PaymentResult::captured(
            amountMinor: $payment->amount_minor,
            externalId: $external,
            payload: ['simulated' => true, 'authcode' => strtoupper(Str::random(6))],
        );
    }

    public function capture(Payment $payment, ?int $amountMinor = null): PaymentResult
    {
        return PaymentResult::captured(
            amountMinor: $amountMinor ?? $payment->amount_minor,
            payload: ['note' => 'Mock provider — capture is a no-op (charged at initiate).'],
        );
    }

    public function refund(Payment $payment, ?int $amountMinor = null, ?string $reason = null): PaymentResult
    {
        return PaymentResult::refunded(
            amountMinor: $amountMinor ?? $payment->amount_minor,
            externalId: 'MOCK-REFUND-' . strtoupper(Str::random(8)),
            payload: ['reason' => $reason, 'simulated' => true],
        );
    }

    public function handleWebhook(string $rawBody, array $headers): PaymentResult
    {
        // Mock provider has no webhooks. Real providers would verify the
        // signature header here, parse the payload, and translate to a result.
        return PaymentResult::failed('Mock provider has no inbound webhooks.');
    }
}
