<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttributeValue> */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    public function definition(): array
    {
        $value = ucfirst($this->faker->unique()->word());
        return [
            'attribute_id' => Attribute::factory(),
            'value'        => $value,
            'position'     => 0,
        ];
    }
}
