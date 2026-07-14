<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    use HasFactory;

    /**
     * Column shape mirrors Phase 1 `addresses` (Gulf-style: block/street/building/
     * floor/apartment + governorate as `state` + lat/lng for delivery) so the
     * snapshot is a faithful copy of whatever was selected from the picker.
     *
     * `recipient_name` is the only field NOT on the source addresses table —
     * Phase 1 addresses don't carry a recipient name (deliveries go to the
     * account holder by default), so we capture it at order placement time.
     */
    protected $fillable = [
        'order_id', 'type',
        'recipient_name', 'phone',
        'country', 'state', 'city',
        'area', 'block', 'street', 'building', 'floor', 'apartment',
        'postal_code', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /** @return BelongsTo<Order, OrderAddress> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Render the address as a single-line string for emails, receipts, etc.
     * Mirrors Address::fullAddressLine() so display is consistent.
     */
    public function singleLine(): string
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
}
