<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'currency', 'subtotal_minor', 'items_count', 'coupon_id', 'discount_minor'];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'items_count'    => 'integer',
            'discount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<User, Cart> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** @return HasMany<CartItem> */
    public function items(): HasMany { return $this->hasMany(CartItem::class)->orderByDesc('id'); }

    /** Phase 9 — applied coupon (nullable) */
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }

    public function isEmpty(): bool { return $this->items_count === 0; }

    /** Phase 9 — payable amount after coupon discount (never negative). */
    public function payableMinor(): int
    {
        return max(0, (int) $this->subtotal_minor - (int) $this->discount_minor);
    }
}
