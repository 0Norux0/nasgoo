<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'countries', 'regions',
        'is_active', 'position', 'description',
    ];

    protected $casts = [
        'countries' => 'array',
        'regions'   => 'array',
        'is_active' => 'boolean',
        'position'  => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ShippingZone $zone) {
            if (empty($zone->slug) && ! empty($zone->name)) {
                $zone->slug = Str::slug($zone->name);
            }
        });
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class)->orderBy('position');
    }

    public function activeMethods(): HasMany
    {
        return $this->methods()->where('is_active', true);
    }

    /**
     * Does this zone cover the given country (and optional region)?
     * Country match is required; if the zone declares regions, the address
     * region must be among them (case-insensitive).
     */
    public function covers(string $countryCode, ?string $region = null): bool
    {
        $countries = array_map('strtoupper', $this->countries ?? []);
        if (! in_array(strtoupper($countryCode), $countries, true)) {
            return false;
        }
        if (empty($this->regions)) {
            return true; // country-wide
        }
        if ($region === null) {
            return false;
        }
        $regions = array_map(fn ($r) => mb_strtolower(trim((string) $r)), $this->regions);
        return in_array(mb_strtolower(trim($region)), $regions, true);
    }
}
