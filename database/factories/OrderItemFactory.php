<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $product = Product::factory()->published()->create();
        $qty = $this->faker->numberBetween(1, 3);
        $lineTotal = $product->price_minor * $qty;
        $pct = 20.00;
        $commission = (int) round(($lineTotal * $pct) / 100);

        return [
            'order_id'                => Order::factory(),
            'vendor_id'               => $product->vendor_id,
            'product_id'              => $product->id,
            'variant_id'              => null,
            'product_name'            => $product->name,
            'product_sku'             => $product->sku,
            'variant_name'            => null,
            'variant_attributes'      => null,
            'quantity'                => $qty,
            'unit_price_minor'        => $product->price_minor,
            'line_total_minor'        => $lineTotal,
            'currency'                => $product->currency,
            'commission_percent'      => $pct,
            'commission_amount_minor' => $commission,
            'vendor_earning_minor'    => $lineTotal - $commission,
            'fulfillment_status'      => OrderItem::FUL_UNFULFILLED,
        ];
    }
}
