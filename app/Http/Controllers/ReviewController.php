<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5 — customer review submission.
 *
 * The customer must own a delivered order_item for the product. Reviews
 * default to `pending` and require admin moderation before appearing on
 * the product page.
 */
class ReviewController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $data = $request->validate([
            'order_item_id' => ['nullable', 'integer'],
            'rating'        => ['required', 'integer', 'min:1', 'max:5'],
            'title'         => ['nullable', 'string', 'max:200'],
            'body'          => ['nullable', 'string', 'max:5000'],
        ]);

        $product = Product::where('slug', $slug)->firstOrFail();
        $user    = $request->user();

        // Validate ownership of a delivered order_item for this product.
        $orderItemQuery = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereNotNull('delivered_at'));

        if (! empty($data['order_item_id'])) {
            $orderItemQuery->whereKey($data['order_item_id']);
        }

        $orderItem = $orderItemQuery->first();
        if (! $orderItem) {
            throw ValidationException::withMessages([
                'order_item_id' => 'You can only review products from delivered orders.',
            ]);
        }

        // Duplicate review for the SAME order_item is blocked at the DB level
        // via the unique index. Catch and surface gracefully.
        $existing = ProductReview::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('order_item_id', $orderItem->id)
            ->first();

        if ($existing) {
            return back()->with('error', 'You have already reviewed this purchase.');
        }

        ProductReview::create([
            'product_id'           => $product->id,
            'user_id'              => $user->id,
            'order_item_id'        => $orderItem->id,
            'rating'               => $data['rating'],
            'title'                => $data['title']  ?? null,
            'body'                 => $data['body']   ?? null,
            'status'               => ProductReview::STATUS_PENDING,
            'is_verified_purchase' => true,
        ]);

        return back()->with('success', 'Thanks for your review! It will appear once approved.');
    }
}
