<?php

declare(strict_types=1);

namespace App\Services\Localization\Providers;

/**
 * Phase 11B.1 v11B.1.2 §7 — default zero-network provider.
 *
 * Creates 'pending' translation rows ready for a human reviewer.
 * Returns null from translate() — there's no auto-generation here.
 *
 * Bound as the default TranslationProviderInterface in AppServiceProvider.
 */
class ManualTranslationProvider implements TranslationProviderInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function translate(string $sourceValue, string $sourceLocale, string $targetLocale): ?string
    {
        // Manual provider never auto-translates. The caller (queue job or
        // admin action) creates a 'pending' row for human attention.
        return null;
    }

    public function autoGenerates(): bool
    {
        return false;
    }
}
