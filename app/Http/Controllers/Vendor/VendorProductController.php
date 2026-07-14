<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Audit\AuditLogger;
use App\Domain\Product\ProductPublishingService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-side product CRUD.
 *
 *  - All routes require an APPROVED vendor (enforced by `vendor:approved` middleware).
 *  - Package limits (max_products, allow_video, allow_3d) are enforced here.
 *  - Status transitions go through ProductPublishingService — vendors can
 *    submit drafts for admin review but cannot self-publish.
 */
class VendorProductController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $products = $vendor->products()
            ->with(['category:id,name', 'primaryImage:id,product_id,path'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $package = $vendor->currentPackage();

        return Inertia::render('Vendor/Products/Index', [
            'vendor' => [
                'id'             => $vendor->id,
                'business_name'  => $vendor->business_name,
            ],
            'products' => $products->through(fn (Product $p) => [
                'id'         => $p->id,
                'slug'       => $p->slug,
                'name'       => $p->name,
                'sku'        => $p->sku,
                'type'       => $p->type,
                'status'     => $p->status,
                'price'      => number_format($p->price_minor / 100, 2) . ' ' . $p->currency,
                'stock'      => $p->stock,
                'category'   => $p->category?->name,
                'thumb'      => $p->primaryImage?->url,
                'created_at' => $p->created_at?->toDateString(),
            ]),
            'limits' => [
                'max_products' => $package?->max_products,
                'current_count' => $vendor->products()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);
        $this->assertWithinProductLimit($request->attributes->get('vendor'));

        return Inertia::render('Vendor/Products/Create', [
            'categories' => Category::where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'name', 'parent_id', 'depth']),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', Product::class);

        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $this->assertWithinProductLimit($vendor);

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'sku'               => ['nullable', 'string', 'max:100'],
            'category_id'       => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'type'              => ['required', Rule::in([Product::TYPE_SIMPLE, Product::TYPE_VARIABLE, Product::TYPE_DIGITAL])],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description'       => ['nullable', 'string'],
            // Phase 11B.1 v11B.1.1 §4 — optional Arabic translations.
            // Vendors can leave these blank; English fallback applies.
            'name_ar'                 => ['nullable', 'string', 'max:255'],
            'short_description_ar'    => ['nullable', 'string', 'max:500'],
            'description_ar'          => ['nullable', 'string'],
            'price_minor'       => ['required', 'integer', 'min:0'],
            'compare_at_price_minor' => ['nullable', 'integer', 'min:0'],
            'cost_price_minor'  => ['nullable', 'integer', 'min:0'],
            'currency'          => ['required', 'string', 'size:3'],
            'track_stock'       => ['boolean'],
            'stock'             => ['nullable', 'integer', 'min:0'],
            'weight_grams'      => ['nullable', 'integer', 'min:0'],
            'meta_title'        => ['nullable', 'string', 'max:255'],
            'meta_description'  => ['nullable', 'string', 'max:500'],

            // Optional images on create
            'images'   => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Phase 10 v10.1 CRITICAL FIX — strip 'images' from the validated
        // payload BEFORE Product::create. The validate() output includes
        // 'images' as an array of UploadedFile objects, but 'images' is NOT
        // a Product column (and shouldn't be — images live in the
        // product_images table). Passing it to Product::create raises
        // Illuminate\Database\Eloquent\MassAssignmentException because
        // 'images' isn't in Product::$fillable (correctly). Uploaded files
        // are read separately from $request->file('images').
        unset($data['images']);

        // Phase 11B.1 v11B.1.1 §4 — fold flat *_ar fields into the JSON
        // translation columns BEFORE create. Empty Arabic input is stored
        // as an empty JSON object (or null array) so it doesn't appear in
        // lookups but won't fail downstream typecasts.
        $data = $this->foldTranslationFields($data);

        // Extract flat *_ar values BEFORE folding strips them — used below
        // to write into the normalized product_translations table.
        $arabicValues = [
            'name'              => $request->input('name_ar'),
            'short_description' => $request->input('short_description_ar'),
            'description'       => $request->input('description_ar'),
        ];

        $product = Product::create(array_merge($data, [
            'vendor_id' => $vendor->id,
            'status'    => Product::STATUS_DRAFT,
        ]));

        // Phase 11B.1 v11B.1.2 §3+§10 — also write Arabic into the
        // normalized product_translations table with status='approved'
        // (vendor-entered content, no review queue needed). The JSON
        // columns remain populated for backward compat.
        $this->persistTranslations($product, 'ar', $arabicValues, $request->user()?->id);

        if ($request->hasFile('images')) {
            $this->storeImages($product, $request->file('images'));
        }

        $audit->log('product.created', $product, after: [
            'vendor_id' => $vendor->id,
            'name'      => $product->name,
            'status'    => $product->status,
        ]);

        return redirect("/vendor/products/{$product->id}/edit")
            ->with('success', 'Product created. Submit it for admin review when ready.');
    }

    public function edit(Request $request, int $product): Response
    {
        $p = $this->resolveOwnProduct($request, $product);
        $this->authorize('update', $p);

        return Inertia::render('Vendor/Products/Edit', [
            'product' => array_merge($p->only([
                'id', 'name', 'slug', 'sku', 'category_id', 'type', 'status',
                'short_description', 'description',
                'price_minor', 'compare_at_price_minor', 'cost_price_minor', 'currency',
                'track_stock', 'stock', 'weight_grams',
                'meta_title', 'meta_description', 'rejection_reason',
            ]), [
                // Phase 11B.1 v11B.1.1 §4 — flatten Arabic JSON values into
                // form-friendly fields for the Edit view. Form posts them
                // back as name_ar / short_description_ar / description_ar
                // and the controller folds them back into JSON columns.
                'name_ar'              => $p->name_translations['ar'] ?? '',
                'short_description_ar' => $p->short_description_translations['ar'] ?? '',
                'description_ar'       => $p->description_translations['ar'] ?? '',
                'translation_status'   => $p->translationStatus('ar'),
                'images' => $p->images->map(fn (ProductImage $i) => [
                    'id'         => $i->id,
                    'path'       => $i->path,
                    'url'        => $i->url,
                    'is_primary' => $i->is_primary,
                ]),
            ]),
            'categories' => Category::where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'name', 'parent_id', 'depth']),

            // Phase 11B.4 v11B.4.2 Defect 9 fix — quality-score badge.
            // Reads pre-generated `vendor_product_quality_scores` (populated
            // by vendor-intelligence:generate). Nullable when the command
            // hasn't yet been run — React handles null gracefully.
            'quality_score' => (function () use ($p) {
                try {
                    $row = \App\Models\VendorProductQualityScore::where('product_id', $p->id)->first();
                    return $row ? [
                        'score'          => (int) $row->score,
                        'missing_fields' => (array) $row->missing_fields,
                        'breakdown'      => (array) $row->score_breakdown,
                        'computed_at'    => $row->computed_at?->toIso8601String(),
                    ] : null;
                } catch (\Throwable) {
                    return null;
                }
            })(),
        ]);
    }

    public function update(Request $request, int $product, AuditLogger $audit): RedirectResponse
    {
        $p = $this->resolveOwnProduct($request, $product);
        $this->authorize('update', $p);

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'sku'               => ['nullable', 'string', 'max:100'],
            'category_id'       => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description'       => ['nullable', 'string'],
            // Phase 11B.1 v11B.1.1 §4 — Arabic translation fields
            'name_ar'                 => ['nullable', 'string', 'max:255'],
            'short_description_ar'    => ['nullable', 'string', 'max:500'],
            'description_ar'          => ['nullable', 'string'],
            'price_minor'       => ['required', 'integer', 'min:0'],
            'compare_at_price_minor' => ['nullable', 'integer', 'min:0'],
            'cost_price_minor'  => ['nullable', 'integer', 'min:0'],
            'currency'          => ['required', 'string', 'size:3'],
            'track_stock'       => ['boolean'],
            'stock'             => ['nullable', 'integer', 'min:0'],
            'weight_grams'      => ['nullable', 'integer', 'min:0'],
            'meta_title'        => ['nullable', 'string', 'max:255'],
            'meta_description'  => ['nullable', 'string', 'max:500'],
            'images'            => ['nullable', 'array', 'max:10'],
            'images.*'          => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $before = ['name' => $p->name, 'price_minor' => $p->price_minor, 'status' => $p->status];

        // Phase 10 v10.1 CRITICAL FIX — same MassAssignmentException
        // root cause as store(). Strip 'images' before $p->update.
        unset($data['images']);

        // Phase 11B.1 v11B.1.2 §3+§10 — capture Arabic values BEFORE the
        // folding helper strips them from $data.
        $arabicValues = [
            'name'              => $request->input('name_ar'),
            'short_description' => $request->input('short_description_ar'),
            'description'       => $request->input('description_ar'),
        ];

        // Phase 11B.1 v11B.1.1 §4 — fold flat *_ar fields into JSON columns,
        // PRESERVING existing translations for other locales (en, ur, etc).
        $data = $this->foldTranslationFields($data, $p);

        $p->update($data);

        // Phase 11B.1 v11B.1.2 §3+§10 — sync the normalized
        // product_translations rows. status='approved' for vendor-entered
        // content. The TranslationService recomputes source_checksum.
        // (Note: the AppServiceProvider saved() observer will also have
        // marked any pre-existing approved translations as 'stale' if
        // the English source columns changed — those stale rows will be
        // overwritten here with the vendor's fresh Arabic input.)
        $this->persistTranslations($p, 'ar', $arabicValues, $request->user()?->id);

        if ($request->hasFile('images')) {
            $this->storeImages($p, $request->file('images'));
        }

        $audit->log('product.updated', $p, $before, [
            'name' => $p->name, 'price_minor' => $p->price_minor,
        ]);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, int $product, AuditLogger $audit): RedirectResponse
    {
        $p = $this->resolveOwnProduct($request, $product);
        $this->authorize('delete', $p);

        $audit->log('product.deleted', $p);
        $p->delete();

        return redirect('/vendor/products')->with('success', 'Product deleted.');
    }

    public function submit(Request $request, int $product, ProductPublishingService $svc): RedirectResponse
    {
        $p = $this->resolveOwnProduct($request, $product);

        // Soft sanity check before sending to admin queue
        if ($p->price_minor <= 0) {
            return back()->withErrors(['price_minor' => 'Set a price before submitting for review.']);
        }
        if ($p->images()->count() === 0) {
            return back()->withErrors(['images' => 'Add at least one image before submitting for review.']);
        }

        $svc->submitForReview($p);

        return back()->with('success', 'Submitted for review. Our team will look at it shortly.');
    }

    /* ─────────────────── helpers ─────────────────── */

    private function resolveOwnProduct(Request $request, int $product): Product
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $p = Product::where('vendor_id', $vendor->id)->find($product);
        if (! $p) {
            throw new NotFoundHttpException();
        }
        return $p;
    }

    private function assertWithinProductLimit(Vendor $vendor): void
    {
        $package = $vendor->currentPackage();
        $limit   = $package?->max_products;
        if ($limit !== null && $vendor->products()->count() >= $limit) {
            abort(403, "Your {$package->name} package allows up to {$limit} products. Upgrade to add more.");
        }
    }

    /**
     * @param array<int, \Illuminate\Http\UploadedFile> $files
     */
    private function storeImages(Product $product, array $files): void
    {
        // v5.4: store on the configured media disk ('public' locally) so the
        // file lands in storage/app/public and is reachable at /storage/...
        // after `storage:link`.
        // v5.6: pass visibility='public' explicitly to the put options so the
        // local driver chmods the file 0644 (otherwise Filament/Livewire
        // intermediate steps can leave a private-visibility file → 403 when
        // /storage/... is requested).
        $disk = config('marketplace.media_disk', 'public');
        $position = $product->images()->max('position') ?? 0;
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach ($files as $file) {
            $path = $file->store(
                "products/{$product->vendor_id}/{$product->id}",
                ['disk' => $disk, 'visibility' => 'public'],
            );
            $position++;
            $isPrimary = ! $hasPrimary;
            $hasPrimary = true;

            ProductImage::create([
                'product_id' => $product->id,
                'path'       => $path,
                'position'   => $position,
                'is_primary' => $isPrimary,
            ]);
        }
    }

    /**
     * Phase 11B.1 v11B.1.1 §4 — fold flat *_ar form fields into JSON cols.
     *
     * Form ships:  name_ar, short_description_ar, description_ar
     * DB stores:   name_translations.ar, short_description_translations.ar,
     *              description_translations.ar
     *
     * Rules:
     *   - Empty/whitespace Arabic input → leave the JSON untouched (don't
     *     create an empty 'ar' key that would interfere with downstream
     *     ?? fallbacks).
     *   - When editing an existing product, PRESERVE other locales (en, ur)
     *     by merging into the existing array.
     *   - When the input is unset entirely (form didn't post it), leave it.
     */
    private function foldTranslationFields(array $data, ?Product $existing = null): array
    {
        $map = [
            'name_ar'              => 'name_translations',
            'short_description_ar' => 'short_description_translations',
            'description_ar'       => 'description_translations',
        ];

        foreach ($map as $flat => $json) {
            if (! array_key_exists($flat, $data)) {
                continue;
            }
            $value = trim((string) ($data[$flat] ?? ''));

            $current = $existing?->{$json};
            if (! is_array($current)) {
                $current = [];
            }

            if ($value !== '') {
                $current['ar'] = $value;
            } else {
                // Empty input → remove 'ar' key (rather than store '')
                unset($current['ar']);
            }

            // Empty array is fine — translatedName() ?? falls back to $this->name
            $data[$json] = $current;
            unset($data[$flat]);
        }

        return $data;
    }

    /**
     * Phase 11B.1 v11B.1.2 §3+§10 — write vendor-entered translations into
     * the normalized product_translations table with status='approved'.
     *
     * Empty/blank values are skipped (no point in writing empty rows).
     * The TranslationService recomputes source_checksum on each write so
     * future source changes can be detected via the saved-observer.
     *
     * @param array<string,?string> $values  field → value map (only 'ar' here)
     */
    private function persistTranslations(Product $product, string $locale, array $values, ?int $reviewerId): void
    {
        $svc = app(\App\Services\Localization\TranslationService::class);
        foreach ($values as $field => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }
            try {
                $svc->setTranslation(
                    product:    $product,
                    locale:     $locale,
                    field:      $field,
                    value:      $value,
                    status:     \App\Models\ProductTranslation::STATUS_APPROVED,
                    provenance: \App\Models\ProductTranslation::SOURCE_MANUAL,
                    reviewerId: $reviewerId,
                );
            } catch (\Throwable $e) {
                // Translation persistence failures must never break product save.
                \Log::warning('v11B.1.2 persistTranslations failed', [
                    'product' => $product->id,
                    'field'   => $field,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
