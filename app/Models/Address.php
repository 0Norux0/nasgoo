<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'label',
        'type',
        'country',
        'state',
        'city',
        'area',
        'block',
        'street',
        'building',
        'floor',
        'apartment',
        'postal_code',
        'phone',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude'   => 'decimal:7',
            'longitude'  => 'decimal:7',
        ];
    }

    /** @return BelongsTo<User, Address> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullAddressLine(): string
    {
        return collect([
            $this->building,
            $this->street,
            $this->block ? "Block {$this->block}" : null,
            $this->area,
            $this->city,
            $this->state,
            $this->country,
        ])->filter()->join(', ');
    }

    protected static function booted(): void
    {
        // When an address is marked default, unset previous default for the same user.
        static::saving(function (Address $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)
                    ->where('id', '!=', $address->id ?? 0)
                    ->update(['is_default' => false]);
            }
        });
    }
}
