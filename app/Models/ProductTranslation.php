<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.1 v11B.1.2 §3 + §6 — normalized translation row.
 *
 * One row per (product, locale, field). The TranslationService consults
 * this table to resolve localized values; admin moderates via Filament.
 */
class ProductTranslation extends Model
{
    use HasFactory;

    /* Per-field status workflow — see migration docblock */
    public const STATUS_MISSING        = 'missing';
    public const STATUS_PENDING        = 'pending';
    public const STATUS_MACHINE_DRAFT  = 'machine_draft';
    public const STATUS_HUMAN_REVIEWED = 'human_reviewed';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_STALE          = 'stale';

    public const STATUSES = [
        self::STATUS_MISSING, self::STATUS_PENDING, self::STATUS_MACHINE_DRAFT,
        self::STATUS_HUMAN_REVIEWED, self::STATUS_APPROVED, self::STATUS_REJECTED,
        self::STATUS_STALE,
    ];

    /* Provenance — see migration docblock */
    public const SOURCE_MANUAL  = 'manual';
    public const SOURCE_IMPORT  = 'import';
    public const SOURCE_MACHINE = 'machine';

    /* Translatable fields supported for products. Keep this list aligned with
       the TranslationService::TRANSLATABLE_FIELDS const so they can't drift. */
    public const FIELDS = ['name', 'short_description', 'description'];

    protected $fillable = [
        'product_id', 'locale', 'field', 'value', 'status',
        'source_provenance', 'source_checksum',
        'reviewed_by', 'translated_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'translated_at' => 'datetime',
            'reviewed_at'   => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * SHA-256 of an English source value. Used to detect stale translations
     * (when source changes after approval).
     */
    public static function checksum(?string $sourceValue): ?string
    {
        if ($sourceValue === null || $sourceValue === '') {
            return null;
        }
        // Normalize trailing whitespace to avoid false stale-detection on
        // formatting-only edits — but preserve internal content sensitivity.
        return hash('sha256', trim($sourceValue));
    }

    /* ─────────────────────── Scopes ─────────────────────── */

    /** Approved translations only — the public storefront default. */
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    /** Approved OR human-reviewed (per dev §13 — policy-dependent). */
    public function scopePublicReady(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_APPROVED, self::STATUS_HUMAN_REVIEWED]);
    }

    public function scopeForLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }

    public function scopeStale(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_STALE);
    }

    public function scopePendingReview(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_MACHINE_DRAFT]);
    }
}
