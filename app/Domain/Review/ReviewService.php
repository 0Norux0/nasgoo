<?php

declare(strict_types=1);

namespace App\Domain\Review;

use App\Domain\Audit\AuditLogger;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 5 — review moderation + rating rollup.
 *
 * Approving a review:
 *   1. flips status to approved
 *   2. recomputes products.rating_avg + rating_count from APPROVED reviews
 *      (so rejected/pending reviews never affect the public number)
 *
 * Rejection just flips status; no rating change.
 */
final class ReviewService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(ProductReview $review, User $admin, ?string $notes = null): ProductReview
    {
        if (! $review->isPending()) {
            throw new RuntimeException("Only pending reviews can be approved (status: {$review->status}).");
        }

        // Phase 9 v9.5 — ROOT CAUSE FIX for "approved review doesn't appear
        // on the product page". AppServiceProvider enables strict mode
        // (Model::shouldBeStrict) in non-production, so any lazy-load
        // throws LazyLoadingViolationException. The Filament resource
        // hands us a ProductReview WITHOUT the `product` relation
        // eager-loaded, so the recomputeProductRating call below would
        // trip the strict-mode guard, the transaction would roll back,
        // and the review would stay pending. The admin sees a Filament
        // error notification (or, worse, the error gets swallowed) and
        // refreshing the product page still shows "No reviews yet".
        //
        // The fix is loadMissing here: it eager-loads the relation only
        // if it isn't already loaded, so callers that DID pre-load it
        // don't pay for a duplicate query.
        $review->loadMissing('product');

        return DB::transaction(function () use ($review, $admin, $notes) {
            $before = $review->toArray();
            $review->update([
                'status'      => ProductReview::STATUS_APPROVED,
                'approved_at' => now(),
            ]);
            $this->recomputeProductRating($review->product);
            $this->audit->log('product_review.approved', $review, $before, $review->fresh()->toArray(), notes: $notes);
            return $review->fresh();
        });
    }

    public function reject(ProductReview $review, User $admin, string $reason): ProductReview
    {
        if (! $review->isPending()) {
            throw new RuntimeException("Only pending reviews can be rejected (status: {$review->status}).");
        }

        return DB::transaction(function () use ($review, $admin, $reason) {
            $before = $review->toArray();
            $review->update([
                'status'           => ProductReview::STATUS_REJECTED,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);
            $this->audit->log('product_review.rejected', $review, $before, $review->fresh()->toArray(), notes: $reason);
            return $review->fresh();
        });
    }

    /** Recompute the public rating fields on the product from approved reviews. */
    public function recomputeProductRating(Product $product): void
    {
        $stats = $product->approvedReviews()
            ->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')
            ->first();

        $product->update([
            'rating_avg'   => round((float) ($stats?->avg ?? 0), 2),
            'rating_count' => (int) ($stats?->cnt ?? 0),
        ]);
    }
}
