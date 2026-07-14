<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 *
 * Mirrors the actual Phase 1 addresses table schema (Gulf-style: country +
 * governorate as `state` + city + area + block + street + building + floor +
 * apartment + postal_code + phone + lat/lng + is_default).
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'label'       => $this->faker->randomElement(['Home', 'Office', 'Mom\'s place']),
            'type'        => 'shipping',
            'country'     => 'KW',
            'state'       => $this->faker->randomElement(['Al Asimah', 'Hawalli', 'Farwaniya', 'Mubarak Al-Kabeer', 'Ahmadi', 'Jahra']),
            'city'        => $this->faker->randomElement(['Kuwait City', 'Salmiya', 'Hawalli', 'Jabriya']),
            'area'        => $this->faker->optional()->word(),
            'block'       => (string) $this->faker->numberBetween(1, 12),
            'street'      => $this->faker->streetName(),
            'building'    => (string) $this->faker->numberBetween(1, 500),
            'floor'       => $this->faker->optional()->numberBetween(1, 10),
            'apartment'   => $this->faker->optional()->bothify('##'),
            'postal_code' => $this->faker->optional()->postcode(),
            'phone'       => '+965' . $this->faker->numerify('########'),
            'is_default'  => false,
        ];
    }

    public function default(): self
    {
        return $this->state(['is_default' => true]);
    }
}
