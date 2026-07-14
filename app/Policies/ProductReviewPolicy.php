<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductReviewPolicy
{
    use HandlesAuthorization;

    /** Admin short-circuit. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_staff'])) {
            return true;
        }
        return null;
    }

    /** Anyone can see the (approved) review list — handled by query scope. */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, ProductReview $review): bool
    {
        if ($review->isApproved()) {
            return true;
        }
        // Owner can see their own pending/rejected review
        return $user !== null && $user->id === $review->user_id;
    }

    /**
     * A customer can submit a review for a product only if they own an
     * OrderItem for that product whose order has been delivered.
     */
    public function createFor(User $user, Product $product, ?int $orderItemId = null): bool
    {
        $query = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereNotNull('delivered_at'));

        if ($orderItemId !== null) {
            $query->whereKey($orderItemId);
        }
        return $query->exists();
    }

    public function update(User $user, ProductReview $review): bool
    {
        // Owner can edit their own review only while still pending
        return $user->id === $review->user_id && $review->isPending();
    }

    public function delete(User $user, ProductReview $review): bool
    {
        return $user->id === $review->user_id;
    }

    /** Admin abilities */
    public function approve(User $user, ProductReview $review): bool { return false; }  // before() handles admin
    public function reject(User $user, ProductReview $review): bool  { return false; }
}
