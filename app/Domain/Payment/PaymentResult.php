<?php

declare(strict_types=1);

namespace App\Domain\Payment;

/**
 * Outcome of a payment-provider operation. PaymentService consumes this and
 * decides which fields to write to the Payment + Order rows inside its
 * transaction. Providers never mutate models directly.
 */
final class PaymentResult
{
    public function __construct(
        public readonly bool $succeeded,
        /** Whether the customer needs to be redirected to complete payment. */
        public readonly bool $requiresAction = false,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $externalId = null,
        public readonly ?string $reference = null,
        /** Provider-specific raw payload, stored on the transaction. */
        public readonly array $payload = [],
        public readonly ?string $errorMessage = null,
        /** When succeeded=true: what payment status should we move to? */
        public readonly string $newStatus = 'captured',
        /** Amount actually moved by this op (capture or refund). */
        public readonly ?int $amountMinor = null,
    ) {}

    public static function captured(int $amountMinor, ?string $externalId = null, array $payload = []): self
    {
        return new self(
            succeeded: true,
            externalId: $externalId,
            payload: $payload,
            newStatus: 'captured',
            amountMinor: $amountMinor,
        );
    }

    public static function pending(int $amountMinor, ?string $reference = null, array $payload = []): self
    {
        return new self(
            succeeded: true,
            externalId: null,
            reference: $reference,
            payload: $payload,
            newStatus: 'pending',
            amountMinor: $amountMinor,
        );
    }

    public static function redirect(string $url, int $amountMinor, ?string $externalId = null, array $payload = []): self
    {
        return new self(
            succeeded: true,
            requiresAction: true,
            redirectUrl: $url,
            externalId: $externalId,
            payload: $payload,
            newStatus: 'authorized',
            amountMinor: $amountMinor,
        );
    }

    public static function refunded(int $amountMinor, ?string $externalId = null, array $payload = []): self
    {
        return new self(
            succeeded: true,
            externalId: $externalId,
            payload: $payload,
            newStatus: 'refunded',
            amountMinor: $amountMinor,
        );
    }

    public static function failed(string $message, array $payload = []): self
    {
        return new self(
            succeeded: false,
            payload: $payload,
            errorMessage: $message,
            newStatus: 'failed',
        );
    }
}
