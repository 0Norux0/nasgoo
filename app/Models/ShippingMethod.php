<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShippingMethod extends Model
{
    use HasFactory;

    public const TYPE_FLAT_RATE = 'flat_rate';
    public const TYPE_FREE      = 'free';
    public const TYPE_PICKUP    = 'pickup';

    protected $fillable = [
        'shipping_zone_id', 'name', 'slug', 'type',
        'fee_minor', 'currency',
        'min_subtotal_minor', 'max_weight_grams',
        'eta_label', 'is_active', 'position', 'description',
    ];

    protected $casts = [
        'fee_minor'          => 'integer',
        'min_subtotal_minor' => 'integer',
        'max_weight_grams'   => 'integer',
        'is_active'          => 'boolean',
        'position'           => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ShippingMethod $method) {
            if (empty($method->slug) && ! empty($method->name)) {
                $method->slug = Str::slug($method->name);
            }
        });
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * Resolve the fee for a given cart subtotal.
     * - free: 0 always (or only if min_subtotal_minor threshold met)
     * - flat_rate: fee_minor
     * - pickup: 0
     */
    public function feeFor(int $subtotalMinor): int
    {
        return match ($this->type) {
            self::TYPE_FREE      => 0, // method's eligibility is the gating concern; see isEligibleFor()
            self::TYPE_PICKUP    => 0,
            self::TYPE_FLAT_RATE => (int) $this->fee_minor,
            default              => (int) $this->fee_minor,
        };
    }

    public function isEligibleFor(int $subtotalMinor, ?int $totalWeightGrams = null): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->type === self::TYPE_FREE && $this->min_subtotal_minor !== null
            && $subtotalMinor < (int) $this->min_subtotal_minor) {
            return false;
        }
        if ($this->max_weight_grams !== null
            && $totalWeightGrams !== null
            && $totalWeightGrams > (int) $this->max_weight_grams) {
            return false;
        }
        return true;
    }
}
