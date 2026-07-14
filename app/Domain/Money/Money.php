<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * Immutable Money value object.
 *
 * All amounts are stored as integer "minor units":
 *   KWD has 3 decimal places → 1 KWD = 1000 fils  → stored as 1000
 *   USD has 2 decimal places → 1 USD = 100 cents  → stored as 100
 *
 * Never use floats for money. Float arithmetic introduces rounding errors
 * that compound over thousands of orders. Integer arithmetic is exact.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,        // minor units (e.g. fils or cents)
        public string $currency,    // ISO 4217 code, e.g. "KWD"
    ) {
        if ($amount < 0) {
            // Allow negative for refunds/debits, but normalize the string
        }
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException("Currency must be a 3-letter ISO code, got: {$currency}");
        }
    }

    /**
     * Build from a major-unit decimal (e.g. 10.500 KWD → 10500 fils).
     *
     * Use this ONLY at boundaries (e.g. user input or external API).
     * Internal math should always use minor units directly.
     */
    public static function fromMajor(string|float|int $major, string $currency, int $decimalPlaces = 2): self
    {
        $multiplier = 10 ** $decimalPlaces;
        $minor = (int) round(((float) $major) * $multiplier);
        return new self($minor, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiply by a percentage. e.g. 20% of 1000 = 200.
     * Result is rounded using banker's rounding (PHP_ROUND_HALF_EVEN).
     */
    public function percentage(float $percent): self
    {
        $result = (int) round(($this->amount * $percent) / 100, 0, PHP_ROUND_HALF_EVEN);
        return new self($result, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /**
     * Render in major units as a string with the right decimal places.
     * e.g. (10500, "KWD", 3 places) → "10.500"
     */
    public function format(int $decimalPlaces = 2): string
    {
        $divisor = 10 ** $decimalPlaces;
        return number_format($this->amount / $divisor, $decimalPlaces, '.', '');
    }

    public function __toString(): string
    {
        return $this->format() . ' ' . $this->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}. "
                ."Convert via CurrencyConverter first."
            );
        }
    }
}
