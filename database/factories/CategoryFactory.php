<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        return [
            'parent_id'  => null,
            'name'       => ucwords((string) $name),
            'description'=> $this->faker->optional()->sentence(),
            'position'   => $this->faker->numberBetween(0, 100),
            'is_active'  => true,
        ];
    }

    public function child(Category $parent): self
    {
        return $this->state(['parent_id' => $parent->id]);
    }

    public function inactive(): self { return $this->state(['is_active' => false]); }
}
