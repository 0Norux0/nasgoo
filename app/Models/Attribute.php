<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'name_translations',
        'type', 'is_filterable', 'is_variation', 'position',
    ];

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'is_filterable'     => 'boolean',
            'is_variation'      => 'boolean',
            'position'          => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Attribute $a) {
            if (empty($a->slug)) {
                $a->slug = Str::slug($a->name);
            }
        });
    }

    /** @return HasMany<AttributeValue> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }

    public function translatedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        return $this->name_translations[$locale] ?? $this->name;
    }
}
