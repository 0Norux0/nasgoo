<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'name',
        'price_minor', 'compare_at_price_minor', 'currency',
        'stock', 'attribute_values',
        'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attribute_values'       => 'array',  // {"color":"red","size":"m"}
            'is_active'              => 'boolean',
            'price_minor'            => 'integer',
            'compare_at_price_minor' => 'integer',
            'stock'                  => 'integer',
            'position'               => 'integer',
        ];
    }

    /** @return BelongsTo<Product, ProductVariant> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductImage> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }
}
