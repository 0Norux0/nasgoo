<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Phase 11B.1 §7 — query normalization.
 *
 * Safe normalization that does NOT change meaning:
 *   - trim leading/trailing whitespace
 *   - collapse repeated internal whitespace
 *   - lowercase (mb-aware)
 *   - remove Arabic diacritics (harakat) — improves matching, preserves meaning
 *   - normalize Arabic letter variants (alef forms, ya/alef-maqsura) — common practice
 *   - cap to max length (defends against unbounded input)
 *
 * What this does NOT do (per dev §7: "Do not use aggressive normalization"):
 *   - English stemming (cars → car) — left to caller if needed
 *   - Removing punctuation — preserves hyphens that may be meaningful
 *   - Translating Arabic ↔ English
 *
 * The returned NORMALIZED string is used for matching only. The DISPLAYED
 * query in the UI remains the user's original input.
 */
class QueryNormalizer
{
    /** Arabic Tatweel character (decorative elongation). */
    private const TATWEEL = "\u{0640}";

    /**
     * Normalize a raw query string for matching.
     */
    public static function normalize(string $raw, int $maxLength = 100): string
    {
        $q = $raw;
        // Trim and cap length first to defend against unbounded input
        $q = trim($q);
        if (mb_strlen($q) > $maxLength) {
            $q = mb_substr($q, 0, $maxLength);
        }

        // Collapse repeated whitespace
        $q = preg_replace('/\s+/u', ' ', $q);

        // Lowercase (mb-aware for Latin; Arabic has no case so unaffected)
        $q = mb_strtolower($q);

        // Strip Arabic diacritics (harakat: fatha, kasra, damma, sukun, etc.)
        // Range U+064B–U+065F is the harakat block.
        $q = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $q);

        // Remove Tatweel (decorative)
        $q = str_replace(self::TATWEEL, '', $q);

        // Normalize alef variants to bare alef (ا) — common Arabic search practice
        // أ إ آ → ا
        $q = preg_replace('/[\x{0623}\x{0625}\x{0622}]/u', "\u{0627}", $q);

        // Normalize alef-maqsura (ى) to ya (ي)
        $q = str_replace("\u{0649}", "\u{064A}", $q);

        // Normalize ta-marbuta (ة) to ha (ه) — debatable; common search practice
        // (commented out by default; meaning-changing for some words)
        // $q = str_replace("\u{0629}", "\u{0647}", $q);

        return $q;
    }

    /**
     * Escape a normalized query for use in a LIKE pattern. Caller adds %.
     */
    public static function escapeLike(string $normalized): string
    {
        return addcslashes($normalized, '%_\\');
    }

    /**
     * Build a prefix LIKE pattern: "query%".
     */
    public static function prefixPattern(string $normalized): string
    {
        return self::escapeLike($normalized) . '%';
    }

    /**
     * Build a substring LIKE pattern: "%query%".
     */
    public static function substringPattern(string $normalized): string
    {
        return '%' . self::escapeLike($normalized) . '%';
    }

    /**
     * Lightweight tokenization on whitespace. Returns words ≥ 2 chars only.
     */
    public static function tokenize(string $normalized): array
    {
        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        return array_values(array_filter(
            $tokens,
            fn ($t) => is_string($t) && mb_strlen($t) >= 2
        ));
    }
}
