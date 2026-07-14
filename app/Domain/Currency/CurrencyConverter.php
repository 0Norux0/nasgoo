<?php

declare(strict_types=1);

namespace App\Domain\Currency;

use App\Domain\Money\Money;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;

final class CurrencyConverter
{
    /**
     * Convert a Money amount to another currency using the most recent rate.
     *
     * Lookup priority:
     *  1. Direct rate (base → target)
     *  2. Inverse rate (target → base, then 1/rate)
     *  3. Triangulate via default currency (base → default → target)
     */
    public function convert(Money $amount, string $targetCurrency): Money
    {
        $targetCurrency = strtoupper($targetCurrency);

        if ($amount->currency === $targetCurrency) {
            return $amount;
        }

        $targetDecimals = $this->decimalsFor($targetCurrency);
        $sourceDecimals = $this->decimalsFor($amount->currency);

        $rate = $this->resolveRate($amount->currency, $targetCurrency);
        if ($rate === null) {
            throw new \RuntimeException(
                "No exchange rate available from {$amount->currency} to {$targetCurrency}"
            );
        }

        // Convert minor units → major float → apply rate → convert back to target minor units
        $sourceMajor = $amount->amount / (10 ** $sourceDecimals);
        $targetMajor = $sourceMajor * $rate;
        $targetMinor = (int) round($targetMajor * (10 ** $targetDecimals));

        return new Money($targetMinor, $targetCurrency);
    }

    private function resolveRate(string $base, string $target): ?float
    {
        $cacheKey = "fx:{$base}:{$target}";

        return Cache::remember($cacheKey, 300, function () use ($base, $target) {
            // Direct
            $direct = CurrencyRate::where('base_currency', $base)
                ->where('target_currency', $target)
                ->where('effective_at', '<=', now())
                ->orderByDesc('effective_at')
                ->first();
            if ($direct) {
                return (float) $direct->rate;
            }

            // Inverse
            $inverse = CurrencyRate::where('base_currency', $target)
                ->where('target_currency', $base)
                ->where('effective_at', '<=', now())
                ->orderByDesc('effective_at')
                ->first();
            if ($inverse && (float) $inverse->rate !== 0.0) {
                return 1 / (float) $inverse->rate;
            }

            // Triangulate via default
            $default = Currency::where('is_default', true)->first();
            if ($default && $base !== $default->code && $target !== $default->code) {
                $a = $this->resolveRate($base, $default->code);
                $b = $this->resolveRate($default->code, $target);
                if ($a !== null && $b !== null) {
                    return $a * $b;
                }
            }

            return null;
        });
    }

    private function decimalsFor(string $currencyCode): int
    {
        return Cache::remember(
            "currency:decimals:{$currencyCode}",
            3600,
            fn () => (int) (Currency::where('code', $currencyCode)->value('decimal_places') ?? 2)
        );
    }
}
