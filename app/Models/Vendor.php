<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vendor extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED    = 'closed';

    protected $fillable = [
        'user_id', 'business_name', 'slug', 'business_email', 'business_phone',
        'business_type', 'description',
        'owner_name', 'owner_email', 'owner_phone',
        'country', 'city', 'address',
        'logo_path', 'banner_path', 'license_document_path', 'id_document_path',
        'commercial_license_no', 'tax_id', 'civil_id',
        'payout_method', 'payout_details',
        'status', 'approved_at', 'approved_by', 'rejection_reason', 'admin_notes',
        'featured', 'featured_until',
        'rating_avg', 'rating_count', 'sales_count',
    ];

    protected function casts(): array
    {
        return [
            'approved_at'     => 'datetime',
            'featured_until'  => 'datetime',
            'featured'        => 'boolean',
            'rating_avg'      => 'decimal:2',
            'rating_count'    => 'integer',
            'sales_count'     => 'integer',
            // payout_details: stored encrypted JSON
            'payout_details'  => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vendor $vendor) {
            if (empty($vendor->slug)) {
                $vendor->slug = self::uniqueSlug($vendor->business_name);
            }
        });
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'vendor-' . Str::random(6);
        }
        $original = $slug;
        $i = 1;
        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }
        return $slug;
    }

    /** @return BelongsTo<User, Vendor> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, Vendor> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<VendorSubscription> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class);
    }

    /** @return HasOne<VendorSubscription> */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(VendorSubscription::class)->where('status', VendorSubscription::STATUS_ACTIVE)->latest('starts_at');
    }

    /** @return HasMany<VendorCommissionRule> */
    public function commissionRules(): HasMany
    {
        return $this->hasMany(VendorCommissionRule::class);
    }

    /** @return HasMany<Product> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Phase 4 — line items the vendor is responsible for fulfilling. */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Phase 5 — payout requests authored by this vendor
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(VendorPayoutRequest::class);
    }

    // Phase 6 — supplier / dropshipping relations
    public function supplierIntegrations(): HasMany   { return $this->hasMany(SupplierIntegration::class); }
    public function supplierProducts(): HasMany       { return $this->hasMany(SupplierProduct::class); }
    public function supplierOrders(): HasMany         { return $this->hasMany(SupplierOrder::class); }
    public function supplierImports(): HasMany        { return $this->hasMany(SupplierProductImport::class); }

    // Phase 8 — service providers (staff) + bookings for this vendor.
    public function serviceProviders(): HasMany       { return $this->hasMany(\App\Models\ServiceProvider::class); }
    public function serviceBookings(): HasMany        { return $this->hasMany(\App\Models\ServiceBooking::class); }

    public function currentPackage(): ?VendorPackage
    {
        return $this->activeSubscription?->package;
    }

    // Status helpers
    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool  { return $this->status === self::STATUS_REJECTED; }
    public function isSuspended(): bool { return $this->status === self::STATUS_SUSPENDED; }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['business_name', 'status', 'featured', 'payout_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('vendor');
    }
}
