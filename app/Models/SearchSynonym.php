<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 11B.1 §8 — admin-managed search synonym dictionary.
 *
 * Stored normalized (lowercase + trimmed). The SynonymService is the sole
 * read consumer and caches the active pairs per-locale.
 */
class SearchSynonym extends Model
{
    use HasFactory;

    protected $fillable = [
        'locale', 'term', 'synonym', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Normalize a term: lowercase + trim + collapse whitespace.
     * Used by writers to enforce consistent storage so duplicates collide.
     */
    public static function normalize(string $term): string
    {
        $term = trim($term);
        $term = preg_replace('/\s+/u', ' ', $term);
        return mb_strtolower($term);
    }

    /**
     * Boot: enforce normalization on save.
     */
    protected static function booted(): void
    {
        static::saving(function (SearchSynonym $s) {
            $s->term    = self::normalize($s->term);
            $s->synonym = self::normalize($s->synonym);
        });
    }
}
