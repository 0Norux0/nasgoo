<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;
    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED = 'fixed_amount';

    protected $fillable = [
        'vendor_id', 'created_by', 'code', 'description',
        'discount_type', 'discount_value',
        'min_order_minor', 'max_discount_minor',
        'starts_at', 'ends_at', 'is_active',
        'usage_limit', 'per_user_limit', 'assigned_user_id', 'currency',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function usages(): HasMany { return $this->hasMany(CouponUsage::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }

    public function scopeUsable(Builder $q): Builder
    {
        $now = Carbon::now();
        return $q->where('is_active', true)
            ->where(fn ($qq) => $qq->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($qq) => $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function computeDiscountMinor(int $subtotalMinor): int
    {
        $raw = match ($this->discount_type) {
            self::DISCOUNT_PERCENTAGE => (int) floor($subtotalMinor * $this->discount_value / 100),
            self::DISCOUNT_FIXED => (int) $this->discount_value,
            default => 0,
        };
        if ($this->max_discount_minor !== null) {
            $raw = min($raw, (int) $this->max_discount_minor);
        }
        return min($raw, $subtotalMinor);
    }
}
