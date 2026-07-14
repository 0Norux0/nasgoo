<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartItem> */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        $product = Product::factory()->published()->create();
        return [
            'cart_id'          => Cart::factory(),
            'product_id'       => $product->id,
            'variant_id'       => null,
            'vendor_id'        => $product->vendor_id,
            'quantity'         => $this->faker->numberBetween(1, 5),
            'unit_price_minor' => $product->price_minor,
            'currency'         => $product->currency,
        ];
    }
}
