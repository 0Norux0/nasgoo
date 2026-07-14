<?php

declare(strict_types=1);

/**
 * Phase 4 v5.1 — explicit coverage of audit item 11 (payment method seeding).
 * The CI tinker step already smoke-checks the three slugs exist; this file
 * additionally pins the seeded providers, currency restrictions, and metadata.
 */

use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodsSeeder;

beforeEach(function () {
    $this->seed(PaymentMethodsSeeder::class);
});

it('item 11: seeds exactly 3 payment methods (cod, manual_transfer, online_mock)', function () {
    expect(PaymentMethod::pluck('slug')->sort()->values()->toArray())
        ->toBe(['cod', 'manual_transfer', 'online_mock']);
});

it('item 11: COD restricts supported currencies to KWD and AED', function () {
    $cod = PaymentMethod::where('slug', 'cod')->firstOrFail();
    expect($cod->provider)->toBe('cod');
    expect($cod->is_active)->toBeTrue();
    expect($cod->available_at_checkout)->toBeTrue();
    expect($cod->supports_currency = $cod->supportsCurrency('KWD'))->toBeTrue();
    expect($cod->supportsCurrency('AED'))->toBeTrue();
    expect($cod->supportsCurrency('USD'))->toBeFalse();
});

it('item 11: manual_transfer accepts all currencies (supported_currencies null)', function () {
    $bt = PaymentMethod::where('slug', 'manual_transfer')->firstOrFail();
    expect($bt->provider)->toBe('manual_transfer');
    expect($bt->supported_currencies)->toBeNull();
    expect($bt->supportsCurrency('KWD'))->toBeTrue();
    expect($bt->supportsCurrency('PKR'))->toBeTrue();
    expect($bt->supportsCurrency('USD'))->toBeTrue();
});

it('item 11: online_mock carries the force_outcome config and translations', function () {
    $mock = PaymentMethod::where('slug', 'online_mock')->firstOrFail();
    expect($mock->provider)->toBe('online_mock');
    expect($mock->config)->toHaveKey('force_outcome');
    expect($mock->config['force_outcome'])->toBe('success');
    // Arabic + Urdu translations present (Gulf-focused i18n)
    expect($mock->name_translations)->toHaveKey('ar')->toHaveKey('ur');
    expect($mock->description_translations)->toHaveKey('ar')->toHaveKey('ur');
});

it('item 11: re-seeding is idempotent (updateOrCreate by slug)', function () {
    $countBefore = PaymentMethod::count();
    $this->seed(PaymentMethodsSeeder::class);
    expect(PaymentMethod::count())->toBe($countBefore);
});

it('item 11: methods are returned in position order at checkout', function () {
    $methods = PaymentMethod::where('available_at_checkout', true)
        ->orderBy('position')
        ->pluck('slug')
        ->toArray();
    // cod (1) -> manual_transfer (2) -> online_mock (3)
    expect($methods)->toBe(['cod', 'manual_transfer', 'online_mock']);
});

it('item 11: translatedName returns the active-locale name when present', function () {
    $cod = PaymentMethod::where('slug', 'cod')->firstOrFail();
    app()->setLocale('ar');
    expect($cod->translatedName())->toBe('الدفع عند الاستلام');
    app()->setLocale('ur');
    expect($cod->translatedName())->toBe('ڈلیوری پر ادائیگی');
    app()->setLocale('en');
    expect($cod->translatedName())->toBe('Cash on Delivery');
});
