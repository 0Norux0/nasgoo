<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Service\ServiceAvailabilityService;
use App\Domain\Service\ServiceBookingService;
use App\Models\Product;
use App\Models\ServiceBooking;
use App\Models\ServiceDetail;
use App\Models\ServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCatalogController extends Controller
{
    /**
     * Customer-facing service browse page.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'service_type'  => ['nullable', 'in:' . implode(',', ServiceDetail::TYPES)],
            'location_mode' => ['nullable', 'in:' . implode(',', ServiceDetail::LOCATION_MODES)],
            'min_price'     => ['nullable', 'numeric', 'min:0'],
            'max_price'     => ['nullable', 'numeric', 'min:0'],
            'area'          => ['nullable', 'string', 'max:100'],
            'q'             => ['nullable', 'string', 'max:120'],
        ]);

        $query = Product::query()
            ->where('type', Product::TYPE_SERVICE)
            ->where('status', 'published')
            ->whereHas('serviceDetail', fn ($q) => $q->where('is_active', true))
            // v7.6 lesson — eager-load everything the response touches.
            ->with(['serviceDetail', 'vendor:id,business_name,slug', 'serviceProviders:id,name']);

        if (! empty($filters['q'])) {
            // Phase 11B.2 v11B.2.1 §2 — search Arabic name_translations as well
            // as the English name column. Mirrors the v11B.1 MarketplaceSearchService
            // pattern but inline here because services use a separate listing path.
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhereRaw("JSON_EXTRACT(name_translations, '$.ar') LIKE ?", [$term])
                  ->orWhereRaw("JSON_EXTRACT(name_translations, '$.en') LIKE ?", [$term]);
            });
        }
        if (! empty($filters['service_type'])) {
            $query->whereHas('serviceDetail', fn ($q) => $q->where('service_type', $filters['service_type']));
        }
        if (! empty($filters['location_mode'])) {
            $query->whereHas('serviceDetail', fn ($q) => $q->where('location_mode', $filters['location_mode']));
        }
        if (! empty($filters['min_price'])) {
            $query->where('price_minor', '>=', ((float) $filters['min_price']) * 100);
        }
        if (! empty($filters['max_price'])) {
            $query->where('price_minor', '<=', ((float) $filters['max_price']) * 100);
        }
        if (! empty($filters['area'])) {
            $query->whereHas('serviceDetail',
                fn ($q) => $q->where('service_area_text', 'like', '%' . $filters['area'] . '%'));
        }

        // v11B.2.1 §2 — eager-load product_translations so translatedDescription()
        // can resolve approved Arabic in a single batched query (no N+1).
        $query->with('translations');

        $services = $query->orderByDesc('id')->paginate(12);

        request()->attributes->set('seo', app(\App\Domain\Seo\SeoBuilder::class)->forServiceListing());
        return Inertia::render('Services/Index', [
            'filters'  => $filters,
            'services' => $services->through(fn (Product $p) => [
                'id'             => $p->id,
                'name'           => $p->translatedName(),
                'slug'           => $p->slug,
                // Phase 11B.2 v11B.2.1 §2 — was `$p->description` (raw English).
                // Now uses translatedShortDescription() which falls back to
                // translatedDescription() via TranslationService; result is
                // locale-aware with controlled English fallback.
                'description'    => (function () use ($p) {
                    $localized = $p->translatedShortDescription() ?: $p->translatedDescription();
                    return $localized ? mb_substr($localized, 0, 200) : null;
                })(),
                'price'          => number_format($p->price_minor / 100, 2),
                'currency'       => $p->currency,
                'duration_min'   => $p->serviceDetail?->duration_minutes,
                'service_type'   => $p->serviceDetail?->service_type,
                'location_mode'  => $p->serviceDetail?->location_mode,
                'service_area'   => $p->serviceDetail?->service_area_text,
                'vendor'         => $p->vendor ? ['id' => $p->vendor->id, 'name' => $p->vendor->business_name, 'slug' => $p->vendor->slug] : null,
                'providers'      => $p->serviceProviders->map(fn ($pr) => $pr->name)->values(),
            ]),
            'service_types'  => ServiceDetail::TYPES,
            'location_modes' => ServiceDetail::LOCATION_MODES,
        ]);
    }

    /**
     * Customer-facing service detail page with availability calendar.
     */
    public function show(Request $request, string $slug, ServiceAvailabilityService $availability): Response
    {
        $service = Product::where('slug', $slug)
            ->where('type', Product::TYPE_SERVICE)
            ->where('status', 'published')
            ->with(['serviceDetail', 'vendor:id,business_name,slug',
                    'serviceProviders' => fn ($q) => $q->where('is_active', true),
                    // v11B.2.1 §2 — eager-load translations for the resolver
                    'translations'])
            ->firstOrFail();

        if (! $service->serviceDetail?->is_active) {
            abort(404);
        }

        // Compute available slots for the next 14 days for the first provider
        // (lightweight preview — customer can switch provider on the page).
        $previewProvider = $service->serviceProviders->first();
        $slotsPreview = [];
        if ($previewProvider) {
            $today = CarbonImmutable::today();
            $end   = $today->addDays(min($service->serviceDetail->max_advance_days, 14));
            $slotsPreview = $availability->slotsForRange($previewProvider, $service, $today, $end);
        }

        request()->attributes->set('seo', app(\App\Domain\Seo\SeoBuilder::class)->forService($service));
        return Inertia::render('Services/Show', [
            'service' => [
                'id'              => $service->id,
                'name'            => $service->translatedName(),
                'slug'            => $service->slug,
                // Phase 11B.2 v11B.2.1 §2 — was `$service->description` (raw EN).
                // Now locale-aware via TranslationService with EN fallback.
                'description'     => $service->translatedDescription(),
                'price'           => number_format($service->price_minor / 100, 2),
                'currency'        => $service->currency,
                'service_type'    => $service->serviceDetail->service_type,
                'location_mode'   => $service->serviceDetail->location_mode,
                'duration_min'    => $service->serviceDetail->duration_minutes,
                'service_area'    => $service->serviceDetail->service_area_text,
                'min_lead_time'   => $service->serviceDetail->min_lead_time_minutes,
                'allow_pick'      => (bool) $service->serviceDetail->allow_customer_provider_pick,
                'requires_address'=> $service->serviceDetail->requiresAddress(),
                'vendor'          => $service->vendor ? ['id' => $service->vendor->id, 'name' => $service->vendor->business_name, 'slug' => $service->vendor->slug] : null,
                'providers'       => $service->serviceProviders->map(fn ($pr) => [
                    'id'             => $pr->id,
                    'name'           => $pr->name,
                    'specialization' => $pr->specialization,
                    'bio'            => $pr->bio,
                ])->values(),
            ],
            'slots_preview_provider_id' => $previewProvider?->id,
            'slots_preview'             => $slotsPreview,
        ]);
    }

    /**
     * AJAX endpoint — slots for a specific provider over a date range.
     * Used by the React calendar when the customer switches provider.
     */
    public function slots(Request $request, ServiceAvailabilityService $availability): array
    {
        $data = $request->validate([
            'service_id'           => ['required', 'integer', 'exists:products,id'],
            'service_provider_id'  => ['required', 'integer', 'exists:service_providers,id'],
            'from'                 => ['required', 'date'],
            'to'                   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $service = Product::with('serviceDetail')
            ->where('type', Product::TYPE_SERVICE)
            ->findOrFail($data['service_id']);
        $provider = ServiceProvider::findOrFail($data['service_provider_id']);

        // Verify assignment
        if (! $service->serviceProviders()->where('service_providers.id', $provider->id)->exists()) {
            return ['slots' => []];
        }

        $from = CarbonImmutable::parse($data['from']);
        $to   = CarbonImmutable::parse($data['to']);

        return ['slots' => $availability->slotsForRange($provider, $service, $from, $to)];
    }
}
