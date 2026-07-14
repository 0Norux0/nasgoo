<?php

declare(strict_types=1);

use App\Domain\Payment\PaymentService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

it('COD initiate stays pending — order not auto-paid', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $cod = PaymentMethod::where('slug', 'cod')->firstOrFail();

    $result = app(PaymentService::class)->initiateFor($order, $cod);

    expect($result->succeeded)->toBeTrue();
    expect($result->newStatus)->toBe('pending');
    expect($order->fresh()->payment_status)->toBe(Order::PAY_PENDING);

    $payment = $order->fresh()->payments()->first();
    expect($payment->status)->toBe(Payment::STATUS_PENDING);
    expect($payment->reference)->toStartWith('COD-');
});

it('COD capture moves order to paid', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $cod = PaymentMethod::where('slug', 'cod')->firstOrFail();
    $svc = app(PaymentService::class);

    $svc->initiateFor($order, $cod);
    $payment = $order->fresh()->payments()->first();
    $svc->capture($payment);

    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
    expect($order->fresh()->payment_status)->toBe(Order::PAY_PAID);
});

it('online_mock initiate captures immediately and marks order paid', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $mock = PaymentMethod::where('slug', 'online_mock')->firstOrFail();

    $result = app(PaymentService::class)->initiateFor($order, $mock);

    expect($result->succeeded)->toBeTrue();
    expect($result->newStatus)->toBe('captured');
    expect($result->externalId)->toStartWith('MOCK-');
    expect($order->fresh()->payment_status)->toBe(Order::PAY_PAID);
    expect($order->fresh()->payments()->first()->status)->toBe(Payment::STATUS_CAPTURED);
});

it('manual_transfer initiate is pending until admin captures', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $bt = PaymentMethod::where('slug', 'manual_transfer')->firstOrFail();
    $svc = app(PaymentService::class);

    $svc->initiateFor($order, $bt);
    $payment = $order->fresh()->payments()->first();
    expect($payment->status)->toBe(Payment::STATUS_PENDING);
    expect($payment->reference)->toStartWith('BT-');
    expect($order->fresh()->payment_status)->toBe(Order::PAY_PENDING);

    $svc->capture($payment);
    expect($payment->fresh()->status)->toBe(Payment::STATUS_CAPTURED);
    expect($order->fresh()->payment_status)->toBe(Order::PAY_PAID);
});

it('full refund moves payment + order to refunded', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $mock = PaymentMethod::where('slug', 'online_mock')->firstOrFail();
    $svc = app(PaymentService::class);

    $svc->initiateFor($order, $mock);
    $payment = $order->fresh()->payments()->first();

    $svc->refund($payment, null, 'customer-return');

    expect($payment->fresh()->status)->toBe(Payment::STATUS_REFUNDED);
    expect($payment->fresh()->refunded_minor)->toBe(5000);
    expect($order->fresh()->payment_status)->toBe(Order::PAY_REFUNDED);
});

it('partial refund leaves payment in partially_refunded state', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 10000]);
    $mock = PaymentMethod::where('slug', 'online_mock')->firstOrFail();
    $svc = app(PaymentService::class);

    $svc->initiateFor($order, $mock);
    $payment = $order->fresh()->payments()->first();
    $svc->refund($payment, 3000, 'partial');

    expect($payment->fresh()->status)->toBe(Payment::STATUS_PARTIAL_REFUND);
    expect($payment->fresh()->refunded_minor)->toBe(3000);
    expect($order->fresh()->payment_status)->toBe(Order::PAY_PARTIAL_REFUND);
});

it('rejects refund exceeding refundable amount', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $mock = PaymentMethod::where('slug', 'online_mock')->firstOrFail();
    $svc = app(PaymentService::class);
    $svc->initiateFor($order, $mock);
    $payment = $order->fresh()->payments()->first();

    expect(fn () => $svc->refund($payment, 9999, 'too-much'))
        ->toThrow(RuntimeException::class);
});

it('writes an append-only payment_transaction row per provider operation', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $mock = PaymentMethod::where('slug', 'online_mock')->firstOrFail();
    $svc = app(PaymentService::class);

    $svc->initiateFor($order, $mock);
    $payment = $order->fresh()->payments()->first();
    $svc->refund($payment, 2000, 'partial');

    expect($payment->fresh()->transactions()->count())->toBe(2);
    expect($payment->fresh()->transactions()->pluck('type')->toArray())
        ->toContain('authorize', 'refund');
});

it('refuses to capture an already-captured payment', function () {
    $order = Order::factory()->pendingPayment()->create(['total_minor' => 5000]);
    $mock = PaymentMethod::where('slug', 'online_mock')->firstOrFail();
    $svc = app(PaymentService::class);
    $svc->initiateFor($order, $mock);
    $payment = $order->fresh()->payments()->first();

    expect(fn () => $svc->capture($payment))->toThrow(RuntimeException::class);
});
