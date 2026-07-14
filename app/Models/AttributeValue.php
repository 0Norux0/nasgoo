<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_id', 'slug', 'value', 'value_translations',
        'color_hex', 'position',
    ];

    protected function casts(): array
    {
        return [
            'value_translations' => 'array',
            'position'           => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AttributeValue $v) {
            if (empty($v->slug)) {
                $v->slug = Str::slug($v->value);
            }
        });
    }

    /** @return BelongsTo<Attribute, AttributeValue> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** @return BelongsToMany<Product> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_value')->withTimestamps();
    }

    public function translatedValue(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        return $this->value_translations[$locale] ?? $this->value;
    }
}
