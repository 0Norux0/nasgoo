<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->randomElement(['cod', 'manual_transfer', 'online_mock', 'extra-' . $this->faker->uuid()]);
        return [
            'slug'                  => $slug,
            'provider'              => in_array($slug, ['cod', 'manual_transfer', 'online_mock'], true) ? $slug : 'online_mock',
            'name'                  => ucfirst(str_replace('_', ' ', $slug)),
            'is_active'             => true,
            'position'              => 0,
            'available_at_checkout' => true,
            'supported_currencies'  => null,
        ];
    }
}
