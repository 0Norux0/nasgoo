<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id', 'product_id', 'variant_id', 'vendor_id',
        'quantity', 'unit_price_minor', 'customization_fee_minor', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity'                => 'integer',
            'unit_price_minor'        => 'integer',
            'customization_fee_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Cart, CartItem> */
    public function cart(): BelongsTo { return $this->belongsTo(Cart::class); }

    /** @return BelongsTo<Product, CartItem> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** @return BelongsTo<ProductVariant, CartItem> */
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }

    /** @return BelongsTo<Vendor, CartItem> */
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }

    /** Phase 7 — customization snapshot rows for this cart line. */
    public function customizations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CartItemCustomization::class);
    }

    /** Phase 7 — true when at least one customization snapshot exists. */
    public function hasCustomizations(): bool
    {
        return $this->customizations()->exists();
    }

    /**
     * Per-line total in minor units.
     *   = (unit_price_minor * quantity) + customization_fee_minor
     *
     * Phase 7: customization_fee_minor is per-LINE (not per-unit). The
     * extra fee for "Add my logo" is charged once per cart line regardless
     * of quantity. If you ever need per-unit customization fees, multiply
     * customization_fee_minor by $this->quantity here.
     */
    public function lineTotalMinor(): int
    {
        return ($this->quantity * $this->unit_price_minor) + (int) ($this->customization_fee_minor ?? 0);
    }
}
