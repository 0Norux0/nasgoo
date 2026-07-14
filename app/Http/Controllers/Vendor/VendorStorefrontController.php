<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorStorefrontController extends Controller
{
    public function show(string $slug): Response
    {
        $vendor = Vendor::where('slug', $slug)
            ->where('status', Vendor::STATUS_APPROVED)
            ->first();

        if (! $vendor) {
            throw new NotFoundHttpException();
        }

        // Load this vendor's published products for display
        $products = $vendor->products()
            ->where('status', \App\Models\Product::STATUS_PUBLISHED)
            ->with(['primaryImage:id,product_id,path', 'category:id,name'])
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->take(24)
            ->get()
            ->map(fn (\App\Models\Product $p) => [
                'slug'      => $p->slug,
                'name'      => $p->name,
                'price'     => number_format($p->price_minor / 100, 2),
                'currency'  => $p->currency,
                'thumb'     => $p->primaryImage?->url,
                'category'  => $p->category?->name,
                'featured'  => $p->featured,
            ]);

        return Inertia::render('Vendor/Storefront', [
            'vendor' => [
                'business_name' => $vendor->business_name,
                'slug'          => $vendor->slug,
                'description'   => $vendor->description,
                'logo_path'     => $vendor->logo_path,
                'banner_path'   => $vendor->banner_path,
                'country'       => $vendor->country,
                'city'          => $vendor->city,
                'rating_avg'    => (float) $vendor->rating_avg,
                'rating_count'  => $vendor->rating_count,
                'sales_count'   => $vendor->sales_count,
                'featured'      => $vendor->featured,
                'created_at'    => $vendor->created_at?->toDateString(),
            ],
            'products' => $products,
        ]);
    }
}
