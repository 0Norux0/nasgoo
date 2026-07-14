<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'source',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'rate'         => 'decimal:8',
            'effective_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Currency, CurrencyRate> */
    public function base(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'code');
    }

    /** @return BelongsTo<Currency, CurrencyRate> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'target_currency', 'code');
    }
}
