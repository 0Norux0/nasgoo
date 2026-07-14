<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id'          => Order::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'method_slug'       => 'cod',
            'provider'          => 'cod',
            'status'            => Payment::STATUS_PENDING,
            'amount_minor'      => 10000,
            'currency'          => 'KWD',
            'refunded_minor'    => 0,
        ];
    }

    public function captured(): self { return $this->state(['status' => Payment::STATUS_CAPTURED, 'captured_at' => now()]); }
    public function refunded(): self { return $this->captured()->state(['status' => Payment::STATUS_REFUNDED, 'refunded_at' => now()]); }
    public function failed(): self   { return $this->state(['status' => Payment::STATUS_FAILED, 'failed_at' => now(), 'failure_reason' => 'declined']); }
}
