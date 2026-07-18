<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.3 v11B.3.1 §4 §14 §15 — canonical site-settings service.
 *
 * Single source of truth for site-wide configuration read by the storefront.
 * Consolidates the existing `Setting` model behind a typed grouped API.
 *
 * KEY DESIGN CHOICES:
 *
 *   1. GROUPED READS. Every request reads at most ONE row per group via
 *      the cache (not one row per setting). `get('branding.site_name')`
 *      → cache-hit → in-memory array access. Pre-v11B.3.1 storefront code
 *      that queried individual settings ran up to 20+ SQL calls per page.
 *
 *   2. LOCALE-AWARE. Translatable settings (branding.tagline,
 *      homepage.hero_title, footer.description) store a locale-keyed
 *      array `{ en: "...", ar: "..." }`. `get('...')` returns the
 *      value for the ACTIVE locale with English fallback.
 *
 *   3. IMMEDIATE INVALIDATION. `set()` writes the row AND flushes the
 *      group cache — no `optimize:clear` needed after admin saves.
 *
 *   4. SAFE DEFAULTS. Missing keys fall back to config defaults
 *      registered in `config/site.php`. No crashes on fresh installs.
 *
 *   5. AUDIT. Every `set()` records `updated_by` (from the authenticated
 *      admin) for the v11B.3.1 §13 audit trail.
 */
class SiteSettingsService
{
    private const CACHE_TTL = 3600;      // 1 hour; invalidated on save
    private const CACHE_KEY_PREFIX = 'site_settings';

    /**
     * Read a single setting value, or a nested key like 'branding.site_name'.
     * Locale-aware for translatable settings.
     *
     * @param  mixed $default  Returned when the setting is absent from DB
     *                          AND config defaults.
     */
    public function get(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        [$group, $subKey] = $this->splitKey($key);
        $groupData = $this->group($group);

        if (! array_key_exists($subKey, $groupData)) {
            // Fall back to config defaults registered in config/site.php
            $default = $default ?? config("site.defaults.{$group}.{$subKey}");
            return $default;
        }

        $value = $groupData[$subKey];

        // Locale resolution for translatable values
        if (is_array($value) && array_key_exists('en', $value)) {
            $locale ??= app()->getLocale();
            return $value[$locale] ?? $value['en'] ?? null;
        }

        return $value;
    }

    /**
     * Read an entire group as an associative array. Used by Inertia share
     * to send `branding`, `footer`, `social`, etc. to the frontend in one
     * shot (no per-setting queries in components).
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $locale = app()->getLocale();
        $cacheKey = self::CACHE_KEY_PREFIX . ":{$group}:v1";

        try {
            $raw = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
                return Setting::where('group', $group)
                    ->get()
                    ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                    ->all();
            });
        } catch (\Throwable $e) {
            // v10.15-style defensive catch — cache driver failure must not
            // 500 the storefront. Fall back to a direct DB read.
            \Log::warning('v11B.3.1 settings cache failed (defensive catch)', ['error' => $e->getMessage()]);
            $raw = Setting::where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all();
        }

        // Merge in config defaults for keys that aren't in DB.
        $defaults = (array) config("site.defaults.{$group}", []);
        $merged = array_merge($defaults, $raw);

        // Resolve translatable values to the active locale for the OUT view
        $resolved = array_map(function ($v) use ($locale) {
            if (is_array($v) && array_key_exists('en', $v)) {
                return $v[$locale] ?? $v['en'] ?? null;
            }
            return $v;
        }, $merged);

        return $this->normalizeGroup($group, $resolved);
    }

    /**
     * Read the RAW (multi-locale) group. Used by the admin UI so it can
     * render both English and Arabic values side-by-side. NOT used for
     * storefront display.
     *
     * @return array<string, mixed>
     */
    public function groupRaw(string $group): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . ":raw:{$group}:v1";
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            return Setting::where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all();
        });
    }

    /**
     * Write one setting value. Handles type inference + immediate cache
     * invalidation. Records updated_by for audit.
     *
     * @param  mixed $value  Any scalar or (for translatable) associative
     *                        array like `['en' => '...', 'ar' => '...']`.
     */
    public function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        [$group, $subKey] = $this->splitKey($key);

        $type = match (true) {
            is_bool($value)                   => 'boolean',
            is_int($value)                    => 'integer',
            is_array($value)                  => 'array',
            default                           => 'string',
        };

        Setting::updateOrCreate(
            ['group' => $group, 'key' => $subKey],
            [
                'value'      => is_scalar($value) ? ['v' => $value] : $value,
                'type'       => $type,
                'updated_by' => $updatedBy,
                'is_translatable' => is_array($value) && array_key_exists('en', $value),
            ]
        );

        $this->invalidate($group);
    }

    /**
     * Update many keys at once inside a single transaction. Preferred by
     * the Filament admin page so a partial save doesn't leak stale-cache
     * inconsistencies.
     *
     * @param  array<string, mixed> $pairs  key => value map
     */
    public function setMany(array $pairs, ?int $updatedBy = null): void
    {
        $groupsTouched = [];
        DB::transaction(function () use ($pairs, $updatedBy, &$groupsTouched) {
            foreach ($pairs as $key => $value) {
                [$group, $subKey] = $this->splitKey($key);
                $groupsTouched[$group] = true;

                $type = match (true) {
                    is_bool($value)  => 'boolean',
                    is_int($value)   => 'integer',
                    is_array($value) => 'array',
                    default          => 'string',
                };

                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $subKey],
                    [
                        'value' => is_scalar($value) ? ['v' => $value] : $value,
                        'type'  => $type,
                        'updated_by' => $updatedBy,
                        'is_translatable' => is_array($value) && array_key_exists('en', $value),
                    ]
                );
            }
        });

        foreach (array_keys($groupsTouched) as $g) {
            $this->invalidate($g);
        }
    }

    /**
     * Reset a group to its config defaults. Wipes the DB rows for the group.
     */
    public function resetGroup(string $group): void
    {
        Setting::where('group', $group)->delete();
        $this->invalidate($group);
    }

    /**
     * Flush all site-settings caches. Called after ANY mutation or by the
     * admin "Clear settings cache" button.
     */
    public function flushAll(): void
    {
        foreach ($this->knownGroups() as $g) {
            $this->invalidate($g);
        }
    }

    /**
     * Every group we ship with a default in config/site.php + any groups
     * that have rows in the DB. Filament tab list comes from here.
     *
     * @return list<string>
     */
    public function knownGroups(): array
    {
        $defaultGroups = array_keys((array) config('site.defaults', []));
        $dbGroups = Setting::query()->distinct()->pluck('group')->all();
        return array_values(array_unique(array_merge($defaultGroups, $dbGroups)));
    }

    /**
     * Grouped payload for Inertia share. Storefront components receive
     *   { branding: {...}, footer: {...}, social: {...}, seo: {...} }
     * with all values already locale-resolved.
     *
     * @return array<string, array<string, mixed>>
     */
    public function publicPayload(): array
    {
        // Only groups that are safe to expose to guests. NEVER expose
        // groups like 'payment', 'shipping', 'commission', 'security'.
        $publicGroups = ['branding', 'appearance', 'header', 'homepage', 'footer', 'contact', 'social', 'seo', 'mobile'];
        $payload = collect($publicGroups)
            ->mapWithKeys(fn ($g) => [$g => $this->group($g)])
            ->all();

        // Phase 11B.4 v11B.4.2 Defect 4 fix — expose vendor_intelligence
        // ONLY the `enabled` flag (never thresholds, weights, or vendor-
        // specific tunables). React uses this to decide whether the panel
        // renders + whether to call /vendor/intelligence at all.
        try {
            $viGroup = $this->group('vendor_intelligence');
            $payload['vendor_intelligence'] = [
                'enabled' => (bool) ($viGroup['enabled'] ?? config('site.defaults.vendor_intelligence.enabled', true)),
            ];
        } catch (\Throwable $e) {
            $payload['vendor_intelligence'] = [
                'enabled' => (bool) config('site.defaults.vendor_intelligence.enabled', true),
            ];
        }

        return $payload;
    }

    /**
     * Normalize public settings so one primary brand image can feed every
     * logo-like surface unless that surface has an explicit override.
     *
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeGroup(string $group, array $values): array
    {
        if ($group === 'branding') {
            $primaryLogo = $this->filledString($values['logo_url'] ?? null);

            if ($primaryLogo) {
                foreach (['logo_dark_url', 'logo_compact_url', 'email_logo_url', 'social_image_url', 'favicon_url'] as $key) {
                    $default = (string) config("site.defaults.branding.{$key}", '');
                    $current = $this->filledString($values[$key] ?? null);

                    if (! $current || $current === $default) {
                        $values[$key] = $primaryLogo;
                    }
                }
            }
        }

        if ($group === 'seo') {
            $defaultOg = $this->filledString($values['default_og_image'] ?? null);
            $brandingOg = $this->filledString($this->group('branding')['social_image_url'] ?? null);
            $configuredDefaultOg = (string) config('site.defaults.seo.default_og_image', '');

            if ((! $defaultOg || $defaultOg === $configuredDefaultOg) && $brandingOg) {
                $values['default_og_image'] = $brandingOg;
            }
        }

        return $values;
    }

    private function filledString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Split "branding.site_name" into ['branding', 'site_name'].
     *
     * @return array{0:string, 1:string}
     */
    private function splitKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        if (count($parts) === 1) {
            return ['general', $parts[0]];
        }
        return [$parts[0], $parts[1]];
    }

    private function invalidate(string $group): void
    {
        try {
            Cache::forget(self::CACHE_KEY_PREFIX . ":{$group}:v1");
            Cache::forget(self::CACHE_KEY_PREFIX . ":raw:{$group}:v1");
        } catch (\Throwable $e) {
            \Log::warning('v11B.3.1 settings cache invalidate failed', ['error' => $e->getMessage()]);
        }
    }
}
