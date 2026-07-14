<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Phase 10 v10.3 — bulletproof MassAssignmentException guard.
     *
     * The developer reported the SAME `MassAssignmentException [images]`
     * error after v10.1, v10.2, and v10.3 attempts to fix specific
     * controllers. The repeated regression strongly suggests there's at
     * least one code path passing `images` into mass assignment that we
     * haven't enumerated (Filament Repeater edge cases, factory builders,
     * future contributions, importers).
     *
     * This override sits at the lowest layer: every mass-assignment
     * attempt — `Product::create([...])`, `$product->fill([...])`,
     * `$product->update([...])`, factory `create(['images' => ...])`,
     * Filament `handleRecordCreation` — flows through `fill()`. We strip
     * 'images' here, BEFORE Eloquent's fillable check. The exception
     * becomes impossible regardless of caller hygiene.
     *
     * Uploaded files for product images live in the product_images
     * table; controllers/Filament Repeater handle them via the explicit
     * `images()` relationship (HasMany ProductImage). The 'images' key
     * stripped here was always an UploadedFile[] from a multipart form,
     * never a real Product column.
     */
    public function fill(array $attributes): static
    {
        unset($attributes['images']);
        return parent::fill($attributes);
    }

    // Lifecycle states
    public const STATUS_DRAFT          = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PUBLISHED      = 'published';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_ARCHIVED       = 'archived';

    // Product types — only simple & variable supported in Phase 3
    public const TYPE_SIMPLE   = 'simple';
    public const TYPE_VARIABLE = 'variable';
    public const TYPE_DIGITAL  = 'digital';
    /** Phase 6 — dropshipping product backed by a supplier_product */
    public const TYPE_DROPSHIP = 'dropship';
    /** Phase 7 — customizable / print-on-demand product */
    public const TYPE_CUSTOM   = 'custom';
    // Phase 8 — services / appointments are products with type=service.
    // See app/Models/ServiceDetail.php for the sibling table holding
    // service-specific fields (duration, location_mode, etc).
    public const TYPE_SERVICE  = 'service';

    public const FULFILLMENT_VENDOR_SELF    = 'vendor_self';
    public const FULFILLMENT_DROPSHIP_MANUAL = 'dropship_manual';
    public const FULFILLMENT_DROPSHIP_ADMIN  = 'dropship_admin';
    public const FULFILLMENT_DROPSHIP_API    = 'dropship_api';

    protected $fillable = [
        'vendor_id', 'category_id',
        'sku', 'slug', 'name', 'name_translations',
        'short_description', 'short_description_translations',
        'description', 'description_translations',
        'type', 'status',
        'approved_at', 'approved_by', 'rejection_reason', 'published_at',
        'price_minor', 'compare_at_price_minor', 'cost_price_minor', 'currency',
        'track_stock', 'stock',
        'weight_grams',
        'featured', 'featured_until',
        'meta_title', 'meta_description',
        'views_count', 'sales_count', 'rating_avg', 'rating_count',
        // Phase 6 — dropshipping
        'supplier_product_id', 'supplier_platform_id', 'supplier_cost_minor',
        'fulfillment_mode', 'estimated_delivery_days',
    ];

    protected function casts(): array
    {
        return [
            'name_translations'              => 'array',
            'short_description_translations' => 'array',
            'description_translations'       => 'array',
            'approved_at'               => 'datetime',
            'published_at'              => 'datetime',
            'featured_until'            => 'datetime',
            'track_stock'               => 'boolean',
            'featured'                  => 'boolean',
            'price_minor'               => 'integer',
            'compare_at_price_minor'    => 'integer',
            'cost_price_minor'          => 'integer',
            'stock'                     => 'integer',
            'weight_grams'              => 'integer',
            'views_count'               => 'integer',
            'sales_count'               => 'integer',
            'rating_avg'                => 'decimal:2',
            'rating_count'              => 'integer',
            // Phase 6 — dropshipping
            'supplier_cost_minor'       => 'integer',
            'estimated_delivery_days'   => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $p) {
            if (empty($p->slug)) {
                $p->slug = self::uniqueSlug($p->name);
            }
        });
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') $slug = 'product-' . Str::random(6);
        $original = $slug;
        $i = 1;
        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }
        return $slug;
    }

    /* ─────────────────────── Relationships ─────────────────────── */

    /** @return BelongsTo<Vendor, Product> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Category, Product> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Category> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withPivot('is_primary')->withTimestamps();
    }

    /** @return HasMany<ProductVariant> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /** @return HasMany<ProductImage> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    // Phase 5 — reviews
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('status', ProductReview::STATUS_APPROVED);
    }

    // Phase 5 — wishlist (which users have wishlisted this product)
    public function wishlistedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists')->withTimestamps();
    }

    /** @return BelongsToMany<AttributeValue> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_value')->withTimestamps();
    }

    /** @return BelongsTo<User, Product> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* ─────────────────────── Status helpers ─────────────────────── */

    public function isDraft(): bool          { return $this->status === self::STATUS_DRAFT; }
    public function isPendingReview(): bool  { return $this->status === self::STATUS_PENDING_REVIEW; }
    public function isPublished(): bool      { return $this->status === self::STATUS_PUBLISHED; }
    public function isRejected(): bool       { return $this->status === self::STATUS_REJECTED; }

    public function isVariable(): bool { return $this->type === self::TYPE_VARIABLE; }
    public function isDropship(): bool { return $this->type === self::TYPE_DROPSHIP; }
    /** Phase 7 — true when product accepts customer customization input. */
    public function isCustomizable(): bool { return $this->type === self::TYPE_CUSTOM; }

    // Phase 8 — service product type + one-to-one service_details relation.
    public function isService(): bool { return $this->type === self::TYPE_SERVICE; }

    public function serviceDetail(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\ServiceDetail::class);
    }

    public function serviceProviders(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\ServiceProvider::class, 'service_provider_assignments')
            ->withTimestamps();
    }

    public function serviceBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ServiceBooking::class);
    }

    // Phase 7 — customization fields relation
    public function customizationFields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductCustomizationField::class)->orderBy('sort_order');
    }
    public function activeCustomizationFields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductCustomizationField::class)->where('is_active', true)->orderBy('sort_order');
    }

    // Phase 6 — dropshipping relations
    public function supplierProduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
    public function supplierPlatform(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SupplierPlatform::class, 'supplier_platform_id');
    }

    /**
     * Available stock — for variable products it's the sum of variants.
     * For simple products, the column value if track_stock=true, else "unlimited".
     */
    public function availableStock(): ?int
    {
        if ($this->isVariable()) {
            return (int) $this->variants()->sum('stock');
        }
        return $this->track_stock ? (int) $this->stock : null;
    }

    /**
     * Phase 11B.1 v11B.1.2 §5 — delegates to TranslationService. The
     * service consults the product_translations table FIRST (approved
     * rows only), then falls back to name_translations JSON (v11A.5/v11B.1
     * data), then to English. All existing callers automatically benefit
     * from the approval workflow without code changes.
     */
    public function translatedName(?string $locale = null): string
    {
        $svc = app(\App\Services\Localization\TranslationService::class);
        return $svc->resolve($this, \App\Services\Localization\TranslationService::FIELD_NAME, $locale)
            ?? (string) $this->name;
    }

    /**
     * Phase 11B.1 v11B.1.2 §3 — normalized translations relation for
     * eager loading. Use ->with('translations') to avoid per-row queries
     * when resolving display fields for a collection of products.
     */
    public function translations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /**
     * Phase 11B.1 v11B.1.2 §5 — delegates to TranslationService (see
     * translatedName() docblock).
     */
    public function translatedShortDescription(?string $locale = null): ?string
    {
        $svc = app(\App\Services\Localization\TranslationService::class);
        return $svc->resolve($this, \App\Services\Localization\TranslationService::FIELD_SHORT_DESCRIPTION, $locale);
    }

    /**
     * Phase 11B.1 v11B.1.2 §5 — delegates to TranslationService (see
     * translatedName() docblock).
     */
    public function translatedDescription(?string $locale = null): ?string
    {
        $svc = app(\App\Services\Localization\TranslationService::class);
        return $svc->resolve($this, \App\Services\Localization\TranslationService::FIELD_DESCRIPTION, $locale);
    }

    /**
     * Phase 11B.1 v11B.1.1 §4 — translation-completeness indicator for the
     * vendor/admin product form. Returns an array of booleans showing which
     * Arabic fields are populated, used to render a "Translation complete"
     * badge or warnings.
     */
    public function translationStatus(string $locale = 'ar'): array
    {
        return [
            'name'              => !empty($this->name_translations[$locale] ?? null),
            'short_description' => !empty($this->short_description_translations[$locale] ?? null),
            'description'       => !empty($this->description_translations[$locale] ?? null),
        ];
    }

    /* ─────────────────────── Scopes ─────────────────────── */

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeForVendor(Builder $q, int $vendorId): Builder
    {
        return $q->where('vendor_id', $vendorId);
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('featured', true)
            ->where(function ($q) {
                $q->whereNull('featured_until')->orWhere('featured_until', '>', now());
            });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'price_minor', 'stock', 'featured'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('product');
    }
}
