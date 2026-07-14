<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ServiceDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VendorServiceController extends Controller
{
    /**
     * List all services for the current vendor.
     */
    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $services = Product::where('vendor_id', $vendor->id)
            ->where('type', Product::TYPE_SERVICE)
            // v7.6 lesson — eager-load relations the view touches.
            ->with(['serviceDetail', 'serviceProviders:id,name'])
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('Vendor/Services/Index', [
            'services' => $services->through(fn (Product $p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'slug'           => $p->slug,
                'price'          => number_format($p->price_minor / 100, 2),
                'currency'       => $p->currency,
                'service_type'   => $p->serviceDetail?->service_type,
                'duration_min'   => $p->serviceDetail?->duration_minutes,
                'location_mode'  => $p->serviceDetail?->location_mode,
                'is_active'      => (bool) $p->serviceDetail?->is_active,
                'providers'      => $p->serviceProviders->map(fn ($pr) => $pr->name)->values(),
                'created_at'     => $p->created_at?->toDateString(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Vendor/Services/Create', [
            'service_types'   => ServiceDetail::TYPES,
            'location_modes'  => ServiceDetail::LOCATION_MODES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:5000'],
            'price'                 => ['required', 'numeric', 'min:0', 'max:99999'],
            'currency'              => ['required', 'string', 'size:3'],
            'service_type'          => ['required', 'in:' . implode(',', ServiceDetail::TYPES)],
            'location_mode'         => ['required', 'in:' . implode(',', ServiceDetail::LOCATION_MODES)],
            'duration_minutes'      => ['required', 'integer', 'min:5', 'max:1440'],
            'service_area_text'     => ['nullable', 'string', 'max:500'],
            'min_lead_time_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'max_advance_days'      => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $product = DB::transaction(function () use ($vendor, $data) {
            $slugBase = Str::slug($data['name']);
            $slug = $slugBase;
            $n = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $n++;
            }

            $sku = strtoupper('SVC-' . $vendor->id . '-' . Str::random(6));

            $product = Product::create([
                'vendor_id'      => $vendor->id,
                'name'           => $data['name'],
                'slug'           => $slug,
                'sku'            => $sku,
                'description'    => $data['description'] ?? null,
                'type'           => Product::TYPE_SERVICE,
                'status'         => 'draft',
                'price_minor'    => (int) round(((float) $data['price']) * 100),
                'currency'       => $data['currency'],
                'stock'          => 0,
                'track_stock'    => false,        // services don't track inventory
            ]);

            ServiceDetail::create([
                'product_id'             => $product->id,
                'service_type'           => $data['service_type'],
                'location_mode'          => $data['location_mode'],
                'duration_minutes'       => $data['duration_minutes'],
                'service_area_text'      => $data['service_area_text'] ?? null,
                'min_lead_time_minutes'  => $data['min_lead_time_minutes'] ?? 0,
                'max_advance_days'       => $data['max_advance_days'] ?? 30,
                'allow_customer_provider_pick' => true,
                'is_active'              => true,
            ]);

            return $product;
        });

        return redirect("/vendor/services/{$product->id}/edit")
            ->with('success', "Service '{$product->name}' created.");
    }

    public function edit(Request $request, int $id): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $service = Product::where('vendor_id', $vendor->id)
            ->where('type', Product::TYPE_SERVICE)
            ->with(['serviceDetail', 'serviceProviders'])
            ->findOrFail($id);

        $allProviders = $vendor->serviceProviders()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Vendor/Services/Edit', [
            'service' => [
                'id'             => $service->id,
                'name'           => $service->name,
                'description'    => $service->description,
                'price'          => number_format($service->price_minor / 100, 2),
                'currency'       => $service->currency,
                'status'         => $service->status,
                'service_type'   => $service->serviceDetail?->service_type,
                'location_mode'  => $service->serviceDetail?->location_mode,
                'duration_min'   => $service->serviceDetail?->duration_minutes,
                'service_area'   => $service->serviceDetail?->service_area_text,
                'is_active'      => (bool) $service->serviceDetail?->is_active,
                'assigned_providers' => $service->serviceProviders->pluck('id')->all(),
            ],
            'all_providers'    => $allProviders,
            'service_types'    => ServiceDetail::TYPES,
            'location_modes'   => ServiceDetail::LOCATION_MODES,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $service = Product::where('vendor_id', $vendor->id)
            ->where('type', Product::TYPE_SERVICE)
            ->with('serviceDetail')
            ->findOrFail($id);

        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:5000'],
            'price'                 => ['required', 'numeric', 'min:0'],
            'status'                => ['required', 'in:draft,published,archived'],
            'service_type'          => ['required', 'in:' . implode(',', ServiceDetail::TYPES)],
            'location_mode'         => ['required', 'in:' . implode(',', ServiceDetail::LOCATION_MODES)],
            'duration_minutes'      => ['required', 'integer', 'min:5', 'max:1440'],
            'service_area_text'     => ['nullable', 'string', 'max:500'],
            'is_active'             => ['boolean'],
            'provider_ids'          => ['array'],
            'provider_ids.*'        => ['integer', 'exists:service_providers,id'],
        ]);

        DB::transaction(function () use ($service, $vendor, $data) {
            $service->update([
                'name'         => $data['name'],
                'description'  => $data['description'] ?? null,
                'price_minor'  => (int) round(((float) $data['price']) * 100),
                'status'       => $data['status'],
            ]);
            $service->serviceDetail->update([
                'service_type'      => $data['service_type'],
                'location_mode'     => $data['location_mode'],
                'duration_minutes'  => $data['duration_minutes'],
                'service_area_text' => $data['service_area_text'] ?? null,
                'is_active'         => (bool) ($data['is_active'] ?? true),
            ]);
            // Sync assigned providers — but only ones owned by this vendor
            $providerIds = $vendor->serviceProviders()
                ->whereIn('id', $data['provider_ids'] ?? [])
                ->pluck('id')->all();
            $service->serviceProviders()->sync($providerIds);
        });

        return back()->with('success', 'Service updated.');
    }
}
