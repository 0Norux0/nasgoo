<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucwords((string) $this->faker->unique()->words(3, true));

        return [
            'vendor_id'         => Vendor::factory()->approved()->withActivePackage(),
            'category_id'       => null, // set explicitly in tests when needed
            'sku'               => strtoupper(Str::random(8)),
            'name'              => $name,
            'short_description' => $this->faker->optional()->sentence(),
            'description'       => $this->faker->optional()->paragraphs(2, true),
            'type'              => Product::TYPE_SIMPLE,
            'status'            => Product::STATUS_DRAFT,
            'price_minor'       => $this->faker->numberBetween(100, 50000),
            'currency'          => 'KWD',
            'track_stock'       => true,
            'stock'             => $this->faker->numberBetween(0, 100),
            'featured'          => false,
            'views_count'       => 0,
            'sales_count'       => 0,
            'rating_avg'        => 0,
            'rating_count'      => 0,
        ];
    }

    public function draft(): self          { return $this->state(['status' => Product::STATUS_DRAFT]); }
    public function pendingReview(): self  { return $this->state(['status' => Product::STATUS_PENDING_REVIEW]); }
    public function published(): self
    {
        return $this->state([
            'status'       => Product::STATUS_PUBLISHED,
            'approved_at'  => now(),
            'published_at' => now(),
        ]);
    }
    public function rejected(): self  { return $this->state(['status' => Product::STATUS_REJECTED, 'rejection_reason' => 'Not allowed in our marketplace']); }
    public function archived(): self  { return $this->state(['status' => Product::STATUS_ARCHIVED]); }
    public function featured(): self  { return $this->state(['featured' => true]); }
    public function variable(): self  { return $this->state(['type' => Product::TYPE_VARIABLE]); }

    public function forCategory(Category $category): self
    {
        return $this->state(['category_id' => $category->id]);
    }

    public function forVendor(Vendor $vendor): self
    {
        return $this->state(['vendor_id' => $vendor->id]);
    }
}
