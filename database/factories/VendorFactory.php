<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $name = fake()->company();
        return [
            'user_id'        => User::factory(),
            'business_name'  => $name,
            'slug'           => Str::slug($name) . '-' . Str::random(4),
            'business_email' => fake()->unique()->safeEmail(),
            'business_phone' => fake()->e164PhoneNumber(),
            'business_type'  => fake()->randomElement(['individual', 'company']),
            'description'    => fake()->paragraph(),
            'country'        => 'KW',
            'city'           => 'Kuwait City',
            'status'         => Vendor::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Vendor::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status'      => Vendor::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Insufficient documentation.'): static
    {
        return $this->state(fn () => [
            'status'           => Vendor::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Vendor::STATUS_SUSPENDED]);
    }

    /**
     * Attach an active subscription on the given package slug after creation.
     * Default 'basic' (30% commission per VendorPackagesSeeder). Phase 4 tests
     * use this to ensure CommissionResolver has a real package to fall back to.
     */
    public function withActivePackage(string $slug = 'basic'): static
    {
        return $this->afterCreating(function (Vendor $vendor) use ($slug) {
            $package = \App\Models\VendorPackage::where('slug', $slug)->first();
            if (! $package) return;
            $vendor->update(['vendor_package_id' => $package->id]);
            \App\Models\VendorSubscription::create([
                'vendor_id'         => $vendor->id,
                'vendor_package_id' => $package->id,
                'status'            => 'active',
                'starts_at'        => now()->subDay(),
            ]);
        });
    }
}
