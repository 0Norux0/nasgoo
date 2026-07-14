<?php

declare(strict_types=1);

use App\Domain\Currency\CurrencyConverter;
use App\Domain\Money\Money;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Database\Seeders\CurrenciesSeeder;

beforeEach(function () {
    $this->seed(CurrenciesSeeder::class);
});

it('seeds four currencies', function () {
    expect(Currency::pluck('code')->toArray())
        ->toContain('KWD')
        ->toContain('USD')
        ->toContain('AED')
        ->toContain('PKR');
});

it('marks KWD as the default currency', function () {
    $default = Currency::where('is_default', true)->first();
    expect($default)->not->toBeNull()
        ->and($default->code)->toBe('KWD');
});

it('enforces a single default currency', function () {
    $usd = Currency::where('code', 'USD')->first();
    $usd->update(['is_default' => true]);

    expect(Currency::where('is_default', true)->count())->toBe(1)
        ->and(Currency::where('is_default', true)->first()->code)->toBe('USD');
});

it('seeds 12 cross-rates', function () {
    expect(CurrencyRate::count())->toBeGreaterThanOrEqual(12);
});

it('uses 3 decimal places for KWD', function () {
    expect(Currency::where('code', 'KWD')->value('decimal_places'))->toBe(3);
});

it('converts KWD to USD via direct rate', function () {
    $converter = app(CurrencyConverter::class);
    // 1 KWD = 1000 fils, rate KWD→USD ≈ 3.25, so result ≈ 3.25 USD = 325 cents
    $kwd = new Money(1000, 'KWD');
    $usd = $converter->convert($kwd, 'USD');

    expect($usd->currency)->toBe('USD')
        ->and($usd->amount)->toBeGreaterThan(300)
        ->and($usd->amount)->toBeLessThan(350);
});

it('returns identical Money when target currency equals source', function () {
    $converter = app(CurrencyConverter::class);
    $usd = new Money(500, 'USD');
    expect($converter->convert($usd, 'USD')->equals($usd))->toBeTrue();
});
