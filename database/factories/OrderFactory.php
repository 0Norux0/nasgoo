<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(1000, 100000);
        return [
            'number'                    => 'MK-2026-' . str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id'                   => User::factory(),
            'status'                    => Order::STATUS_PENDING_PAYMENT,
            'payment_status'            => Order::PAY_PENDING,
            'fulfillment_status'        => Order::FUL_UNFULFILLED,
            'currency'                  => 'KWD',
            'subtotal_minor'            => $subtotal,
            'shipping_minor'            => 0,
            'tax_minor'                 => 0,
            'discount_minor'            => 0,
            'total_minor'               => $subtotal,
            'platform_commission_minor' => 0,
            'vendor_earnings_minor'     => $subtotal,
        ];
    }

    public function pendingPayment(): self { return $this->state(['status' => Order::STATUS_PENDING_PAYMENT, 'payment_status' => Order::PAY_PENDING]); }
    public function paid(): self
    {
        return $this->state([
            'status' => Order::STATUS_PAID, 'payment_status' => Order::PAY_PAID, 'paid_at' => now(),
        ]);
    }
    public function confirmed(): self    { return $this->paid()->state(['status' => Order::STATUS_CONFIRMED, 'confirmed_at' => now()]); }
    public function shipped(): self      { return $this->confirmed()->state(['status' => Order::STATUS_SHIPPED, 'shipped_at' => now(), 'fulfillment_status' => Order::FUL_FULFILLED]); }
    public function delivered(): self    { return $this->shipped()->state(['status' => Order::STATUS_DELIVERED, 'delivered_at' => now(), 'earnings_release_at' => now()->addDays(7)]); }
    public function cancelled(): self    { return $this->state(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]); }
}
