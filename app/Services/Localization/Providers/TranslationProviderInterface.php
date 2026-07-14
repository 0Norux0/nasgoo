<?php

declare(strict_types=1);

namespace App\Services\Localization\Providers;

/**
 * Phase 11B.1 v11B.1.2 §7 — translation provider abstraction.
 *
 * Per dev §7:
 *   - "Do not integrate a paid AI or paid translation API"
 *   - "Build a provider interface"
 *   - "The marketplace must function without any external provider"
 *
 * The default binding (in AppServiceProvider) is ManualTranslationProvider —
 * a no-op generator that creates translation rows with status='pending'
 * waiting for human input. Future bindings could be:
 *   - ImportTranslationProvider     (CSV/XLSX upload)
 *   - SelfHostedTranslationProvider (LibreTranslate, etc., behind a feature flag)
 *
 * No implementation in v11B.1.2 ships network calls or paid API integrations.
 */
interface TranslationProviderInterface
{
    /**
     * Return a human-readable identifier (e.g. 'manual', 'import', 'libre').
     */
    public function name(): string;

    /**
     * Attempt to translate $sourceValue from $sourceLocale to $targetLocale.
     *
     * MUST return null when this provider cannot supply a translation (e.g.
     * the manual provider, which never auto-generates). The caller (queued
     * job or admin action) is responsible for any persistence.
     *
     * MUST NOT throw on missing config, network unavailability, or rate-
     * limit responses — return null and log instead. Translation generation
     * failures must never break the storefront.
     */
    public function translate(string $sourceValue, string $sourceLocale, string $targetLocale): ?string;

    /**
     * Whether this provider auto-generates translations (true for machine
     * adapters) or requires human input (false for manual/import).
     *
     * Drives the queue job's status decision:
     *   - autoGenerates() == true && translate() returns string → status=machine_draft
     *   - autoGenerates() == false → status=pending until human review
     */
    public function autoGenerates(): bool;
}
