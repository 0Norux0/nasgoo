<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorReviewController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $reviews = ProductReview::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with(['product:id,slug,name', 'user:id,name'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Vendor/Reviews/Index', [
            'reviews' => $reviews->through(fn (ProductReview $r) => [
                'id'                   => $r->id,
                'product_name'         => $r->product?->name,
                'product_slug'         => $r->product?->slug,
                'customer_name'        => $r->user?->name,
                'rating'               => $r->rating,
                'title'                => $r->title,
                'body'                 => $r->body,
                'status'               => $r->status,
                'is_verified_purchase' => $r->is_verified_purchase,
                'created_at'           => $r->created_at?->toDateTimeString(),
            ]),
        ]);
    }
}
