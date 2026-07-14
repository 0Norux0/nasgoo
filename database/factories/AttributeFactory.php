<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Attribute> */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->word());
        return [
            'slug'          => Str::slug($name) . '-' . Str::random(4),
            'name'          => $name,
            'type'          => 'select',
            'is_filterable' => true,
            'is_variation'  => false,
            'position'      => 0,
        ];
    }
}
