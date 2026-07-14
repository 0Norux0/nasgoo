<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 5 — customer wishlist.
 *
 * All endpoints require authentication (router middleware). Guests visiting
 * the wishlist URL are redirected to /login by the auth middleware.
 */
class WishlistController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Eager-load every relation the presenter touches to satisfy strict mode
        // with multi-item wishlists.
        $entries = $user->wishlist()
            ->with(['product.primaryImage', 'product.vendor:id,slug,business_name'])
            ->latest()
            ->paginate(24);

        return Inertia::render('Wishlist/Index', [
            'wishlist' => $entries->through(fn (Wishlist $w) => [
                'id'           => $w->id,
                'added_at'     => $w->created_at?->toDateTimeString(),
                'product'      => $w->product ? [
                    'id'        => $w->product->id,
                    'slug'      => $w->product->slug,
                    'name'      => $w->product->name,
                    'price'     => number_format($w->product->price_minor / 100, 2),
                    'currency'  => $w->product->currency,
                    'thumb'     => $w->product->primaryImage?->url,
                    'status'    => $w->product->status,
                    'in_stock'  => ! $w->product->track_stock || $w->product->stock > 0,
                    'vendor'    => $w->product->vendor ? [
                        'slug'          => $w->product->vendor->slug,
                        'business_name' => $w->product->vendor->business_name,
                    ] : null,
                ] : null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        // firstOrCreate handles the duplicate-prevention atomically even under
        // concurrent requests (relies on the unique index).
        Wishlist::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return back()->with('success', "Added '{$product->name}' to your wishlist.");
    }

    public function destroy(Request $request, int $product): RedirectResponse
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product)
            ->delete();

        return back()->with('success', 'Removed from wishlist.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->user()->wishlist()->delete();
        return back()->with('success', 'Wishlist cleared.');
    }
}
