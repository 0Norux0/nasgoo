<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MarketplaceMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'variant_id',
        'path', 'alt_text', 'position', 'is_primary',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'position'   => 'integer',
        ];
    }

    /**
     * Public URL for the stored image. Resolves through the configured media
     * disk (public locally, R2/MinIO in production). Returns null if no path.
     *
     * v5.4: previously controllers emitted the raw `path` and the frontend
     * printed it as text — images never actually rendered. Now we emit a
     * ready-to-use URL.
     */
    public function getUrlAttribute(): ?string
    {
        $path = MarketplaceMedia::publicPathOrExternalUrl($this->path);

        if (empty($path)) {
            return null;
        }

        // Absolute URLs (e.g. already-CDN paths) pass through untouched.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (config('marketplace.media_disk', 'public') === 'public') {
            return MarketplaceMedia::publicUrl($path);
        }

        return Storage::disk(config('marketplace.media_disk', 'public'))->url($path);
    }

    public function setPathAttribute(?string $value): void
    {
        $this->attributes['path'] = MarketplaceMedia::publicPathOrExternalUrl($value);
    }

    /** @return BelongsTo<Product, ProductImage> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, ProductImage> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
