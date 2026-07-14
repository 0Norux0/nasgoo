<?php

declare(strict_types=1);

namespace App\Domain\Supplier;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 — SupplierProductMapper.
 *
 * Converts a SupplierProduct into a marketplace Product, status `pending`
 * (awaiting admin approval — same flow as a regular vendor-created product).
 * The supplier_product.import_status moves pending → mapped.
 *
 * When admin approves the Product, the SupplierProduct status moves
 * mapped → published. Reject moves it to rejected.
 */
class SupplierProductMapper
{
    public function map(SupplierProduct $supplierProduct, array $overrides, User $actor): Product
    {
        return DB::transaction(function () use ($supplierProduct, $overrides, $actor) {
            $sellingPriceMinor = (int) ($overrides['price_minor'] ?? $supplierProduct->supplier_cost_minor);
            if ($sellingPriceMinor < $supplierProduct->supplier_cost_minor) {
                throw new \InvalidArgumentException('Selling price must be greater than or equal to supplier cost.');
            }

            $categoryId = $overrides['category_id'] ?? null;

            $product = Product::create([
                'vendor_id'                => $supplierProduct->vendor_id,
                'category_id'              => $categoryId,
                'supplier_product_id'      => $supplierProduct->id,
                'supplier_platform_id'     => $supplierProduct->supplier_platform_id,
                'sku'                      => $overrides['sku'] ?? $this->generateSku($supplierProduct),
                'name'                     => $overrides['name'] ?? $supplierProduct->title,
                'short_description'        => $overrides['short_description'] ?? null,
                'description'              => $overrides['description'] ?? $supplierProduct->description,
                'type'                     => Product::TYPE_DROPSHIP,
                'status'                   => Product::STATUS_PENDING_REVIEW,
                'price_minor'              => $sellingPriceMinor,
                'cost_price_minor'         => $supplierProduct->supplier_cost_minor,
                'supplier_cost_minor'      => $supplierProduct->supplier_cost_minor,
                'currency'                 => $overrides['currency'] ?? 'KWD',
                'track_stock'              => true,
                'stock'                    => (int) ($overrides['stock'] ?? ($supplierProduct->supplier_stock_qty ?? 0)),
                'fulfillment_mode'         => $overrides['fulfillment_mode'] ?? Product::FULFILLMENT_DROPSHIP_MANUAL,
                'estimated_delivery_days'  => $overrides['estimated_delivery_days'] ?? $supplierProduct->estimated_delivery_days,
            ]);

            $supplierProduct->update([
                'product_id'    => $product->id,
                'import_status' => SupplierProduct::STATUS_MAPPED,
                'mapped_at'     => now(),
            ]);

            return $product;
        });
    }

    public function publish(SupplierProduct $supplierProduct, User $admin): void
    {
        if (! $supplierProduct->product_id) {
            throw new \LogicException('Cannot publish a supplier product that has not been mapped yet.');
        }

        DB::transaction(function () use ($supplierProduct, $admin) {
            $product = Product::findOrFail($supplierProduct->product_id);
            $product->update([
                'status'        => Product::STATUS_PUBLISHED,
                'approved_at'   => now(),
                'approved_by'   => $admin->id,
                'published_at'  => now(),
            ]);
            $supplierProduct->update([
                'import_status' => SupplierProduct::STATUS_PUBLISHED,
                'published_at'  => now(),
            ]);
        });
    }

    public function reject(SupplierProduct $supplierProduct, string $reason, User $admin): void
    {
        DB::transaction(function () use ($supplierProduct, $reason, $admin) {
            if ($supplierProduct->product_id) {
                Product::where('id', $supplierProduct->product_id)->update([
                    'status'             => Product::STATUS_REJECTED,
                    'rejection_reason'   => $reason,
                ]);
            }
            $supplierProduct->update([
                'import_status' => SupplierProduct::STATUS_REJECTED,
                'import_notes'  => trim(($supplierProduct->import_notes ? $supplierProduct->import_notes . "\n" : '') . "REJECTED by {$admin->email}: {$reason}"),
            ]);
        });
    }

    private function generateSku(SupplierProduct $sp): string
    {
        return sprintf(
            'DRP-%s-%s',
            strtoupper(substr($sp->platform?->slug ?? 'sup', 0, 4)),
            strtoupper(substr(bin2hex(random_bytes(4)), 0, 8))
        );
    }
}
