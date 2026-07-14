<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    use HasFactory;
    public const TYPE_DEAL_OF_DAY = 'deal_of_day';
    public const TYPE_FLASH_SALE = 'flash_sale';
    public const TYPE_LIMITED_TIME = 'limited_time';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_VENDOR = 'vendor';
    public const TYPE_PRODUCT_SPECIFIC = 'product_specific';
    public const TYPE_FREE_SHIPPING = 'free_shipping';
    public const TYPE_SERVICE_SPECIFIC = 'service_specific';

    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED = 'fixed_amount';
    public const DISCOUNT_FREE_SHIP = 'free_shipping';

    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'vendor_id', 'created_by', 'title', 'slug', 'description',
        'promotion_type', 'discount_type', 'discount_value',
        'starts_at', 'ends_at', 'is_active',
        'usage_limit', 'per_customer_limit',
        'min_order_minor', 'max_discount_minor',
        'approval_status', 'rejection_reason', 'currency',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function targets(): HasMany { return $this->hasMany(PromotionTarget::class); }

    public function scopeUsable(Builder $q): Builder
    {
        $now = Carbon::now();
        return $q->where('is_active', true)
            ->where('approval_status', self::APPROVAL_APPROVED)
            ->where(fn ($qq) => $qq->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($qq) => $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function computeDiscountMinor(int $lineSubtotalMinor): int
    {
        if ($this->discount_type === self::DISCOUNT_FREE_SHIP) return 0;
        $raw = match ($this->discount_type) {
            self::DISCOUNT_PERCENTAGE => (int) floor($lineSubtotalMinor * $this->discount_value / 100),
            self::DISCOUNT_FIXED => (int) $this->discount_value,
            default => 0,
        };
        if ($this->max_discount_minor !== null) {
            $raw = min($raw, (int) $this->max_discount_minor);
        }
        return min($raw, $lineSubtotalMinor);
    }
}
