<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_default'     => 'boolean',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ];
    }

    /** @return HasMany<CurrencyRate> */
    public function ratesFrom(): HasMany
    {
        return $this->hasMany(CurrencyRate::class, 'base_currency', 'code');
    }

    /** @return HasMany<CurrencyRate> */
    public function ratesTo(): HasMany
    {
        return $this->hasMany(CurrencyRate::class, 'target_currency', 'code');
    }

    protected static function booted(): void
    {
        static::saving(function (Currency $currency) {
            // Enforce a single default currency
            if ($currency->is_default) {
                static::where('code', '!=', $currency->code)
                    ->update(['is_default' => false]);
            }
        });
    }
}
