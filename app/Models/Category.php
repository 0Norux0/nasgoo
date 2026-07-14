<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'slug', 'name', 'name_translations', 'description',
        'icon_path', 'image_path',
        'depth', 'path',
        'position', 'is_active',
        'products_count',
    ];

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'is_active'         => 'boolean',
            'depth'             => 'integer',
            'position'          => 'integer',
            'products_count'    => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $c) {
            if (empty($c->slug)) {
                $c->slug = self::uniqueSlug($c->name);
            }
        });

        static::saving(function (Category $c) {
            // Compute depth + path from parent
            if ($c->parent_id) {
                $parent = self::find($c->parent_id);
                $c->depth = $parent ? $parent->depth + 1 : 0;
                $c->path  = $parent ? trim(($parent->path ?? $parent->slug) . '/' . $c->slug, '/') : $c->slug;
            } else {
                $c->depth = 0;
                $c->path  = $c->slug;
            }
        });
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') $slug = 'category-' . Str::random(6);
        $original = $slug;
        $i = 1;
        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }
        return $slug;
    }

    /** @return BelongsTo<Category, Category> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /** @return HasMany<Product> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Additional (non-primary) products via the pivot. */
    public function allProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product')->withPivot('is_primary')->withTimestamps();
    }

    /**
     * Translated name — falls back to canonical English `name` if locale missing.
     */
    public function translatedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        return $this->name_translations[$locale] ?? $this->name;
    }
}
