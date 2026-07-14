<?php

declare(strict_types=1);

namespace App\Domain\Payment\Providers;

use App\Domain\Payment\PaymentResult;
use App\Models\Order;
use App\Models\Payment;

/**
 * Payment provider contract.
 *
 * Every payment integration (CashOnDelivery, ManualBankTransfer, online gateways
 * like MyFatoorah/Tap/Stripe in future sub-phases) implements this. The contract
 * is intentionally minimal so adding a real gateway later only requires:
 *   1. Implementing this interface
 *   2. Registering the class in App\Domain\Payment\PaymentProviderRegistry
 *   3. Adding a row to payment_methods with provider = the registered name
 *
 * Provider implementations must NEVER mutate Order or Payment status fields
 * directly — they return a PaymentResult and let PaymentService persist the
 * state change inside its transaction. This keeps the audit trail consistent.
 */
interface PaymentProvider
{
    /** Stable identifier matching payment_methods.provider. */
    public static function name(): string;

    /**
     * Begin the payment. For sync providers (COD), this returns succeeded=true
     * immediately. For redirect providers (online gateways), returns
     * requiresAction=true with a redirect URL.
     */
    public function initiate(Order $order, Payment $payment, array $context = []): PaymentResult;

    /**
     * Capture or finalise an authorised payment. For sync providers this is
     * a no-op that succeeds. For pre-auth providers this triggers capture.
     */
    public function capture(Payment $payment, ?int $amountMinor = null): PaymentResult;

    /**
     * Issue a refund for the given amount (defaults to full refund).
     */
    public function refund(Payment $payment, ?int $amountMinor = null, ?string $reason = null): PaymentResult;

    /**
     * Process an inbound webhook from the provider. The headers + raw body
     * are passed verbatim so each provider can verify its own signature.
     */
    public function handleWebhook(string $rawBody, array $headers): PaymentResult;
}
