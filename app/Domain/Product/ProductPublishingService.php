<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Audit\AuditLogger;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Centralised product lifecycle service so admins (Filament + future API)
 * cannot accidentally publish without status/approval bookkeeping +
 * category-counter maintenance.
 */
final class ProductPublishingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Vendor moves draft → pending_review. The product is now visible to
     * admins in the approval queue but still hidden from the public.
     */
    public function submitForReview(Product $product): Product
    {
        if (! in_array($product->status, [Product::STATUS_DRAFT, Product::STATUS_REJECTED], true)) {
            return $product;
        }

        $before = ['status' => $product->status];
        $product->status = Product::STATUS_PENDING_REVIEW;
        $product->rejection_reason = null;
        $product->save();

        $this->audit->log('product.submitted', $product, $before, ['status' => $product->status]);
        return $product->fresh();
    }

    /**
     * Admin publishes a pending product:
     *  - flips status → published
     *  - stamps approved_at / approved_by / published_at
     *  - increments category products_count
     *  - audit logs
     */
    public function publish(Product $product, ?int $approverId = null): Product
    {
        return DB::transaction(function () use ($product, $approverId) {
            $before = ['status' => $product->status];

            $product->status        = Product::STATUS_PUBLISHED;
            $product->approved_at   = now();
            $product->approved_by   = $approverId ?? Auth::id();
            $product->published_at  = $product->published_at ?? now();
            $product->rejection_reason = null;
            $product->save();

            // Bump category counter (primary + extra categories)
            $this->incrementCategoryCounter($product);

            $this->audit->log('product.published', $product, $before, [
                'status' => $product->status,
                'vendor_id' => $product->vendor_id,
            ]);

            return $product->fresh();
        });
    }

    public function reject(Product $product, string $reason, ?int $rejectorId = null): Product
    {
        $before = ['status' => $product->status];

        $product->status            = Product::STATUS_REJECTED;
        $product->rejection_reason  = $reason;
        $product->approved_by       = $rejectorId ?? Auth::id();
        $product->save();

        $this->audit->log('product.rejected', $product, $before, [
            'status' => $product->status,
            'reason' => $reason,
        ]);

        return $product->fresh();
    }

    /**
     * Archive removes the product from public listings but preserves history.
     * Decrements the category counter if it was previously published.
     */
    public function archive(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $before = ['status' => $product->status];
            $wasPublished = $product->isPublished();

            $product->status = Product::STATUS_ARCHIVED;
            $product->save();

            if ($wasPublished) {
                $this->decrementCategoryCounter($product);
            }

            $this->audit->log('product.archived', $product, $before, ['status' => $product->status]);
            return $product->fresh();
        });
    }

    /**
     * Restore an archived product to draft so the vendor can edit and resubmit.
     */
    public function restoreToDraft(Product $product): Product
    {
        $before = ['status' => $product->status];
        $product->status = Product::STATUS_DRAFT;
        $product->save();
        $this->audit->log('product.restored', $product, $before, ['status' => $product->status]);
        return $product->fresh();
    }

    private function incrementCategoryCounter(Product $product): void
    {
        if ($product->category_id) {
            Category::where('id', $product->category_id)->increment('products_count');
        }
    }

    private function decrementCategoryCounter(Product $product): void
    {
        if ($product->category_id) {
            Category::where('id', $product->category_id)
                ->where('products_count', '>', 0)
                ->decrement('products_count');
        }
    }
}
