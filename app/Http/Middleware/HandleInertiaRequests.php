<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $translations = $this->loadTranslations($locale);

        return [
            ...parent::share($request),

            'app' => [
                'name'      => config('app.name'),
                'url'       => config('app.url'),
                'locale'    => $locale,
                'direction' => $locale === 'ar' || $locale === 'ur' ? 'rtl' : 'ltr',
                // Phase 10 v10.2 — expose the version so the React layout can
                // display a visible banner. Without this, the developer cannot
                // tell from the browser whether the deployed code matches the
                // package version. Read from the VERSION file with a 1-hour
                // cache (rewritten on every deploy).
                //
                // Phase 10 v10.15 — defensive try/catch. This was preexisting
                // code (not v10.14) but it sits in the auth-critical path —
                // EVERY Inertia render including /login itself reads it. If
                // the cache driver throws, even the login page can't render
                // and the dev sees "can't log in at all". Fallback to direct
                // file read keeps the login page (and post-login redirects)
                // working even if cache is broken.
                'version'   => (function () {
                    try {
                        return \Illuminate\Support\Facades\Cache::remember(
                            'marketplace:version',
                            now()->addHour(),
                            fn () => trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unknown'
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning(
                            'app.version cache failed (Phase 10 v10.15 defensive catch)',
                            ['message' => $e->getMessage()]
                        );
                        return trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unknown';
                    }
                })(),
            ],

            'marketplace' => [
                'default_currency'     => config('marketplace.default_currency'),
                'supported_currencies' => config('marketplace.supported_currencies'),
                'supported_locales'    => config('marketplace.supported_locales'),
                'guest_browsing'       => config('marketplace.guest_browsing'),
                'guest_checkout'       => config('marketplace.guest_checkout'),
            ],

            // v3.3 — translations shared with every page for client-side t().
            // We send a flat key→string map (no nesting) for the locale that
            // was resolved by App\Http\Middleware\SetLocale, falling back to
            // English for any missing keys at render time.
            'translations' => $translations,

            // Phase 10 — SEO shared block. Per-page SEO comes from
            // $request->attributes->get('seo') (set by controllers); we
            // merge in marketplace-wide defaults so every page has at least
            // a usable title + description + canonical + OG/Twitter set.
            // The React layouts read `seo.*` and emit <Head> meta tags.
            'seo' => function () use ($request) {
                $perPage = (array) ($request->attributes->get('seo') ?? []);
                $appName = (string) config('app.name', 'Marketplace');
                $defaultTitle = $appName;
                $defaultDesc = (string) config(
                    'marketplace.seo_default_description',
                    'Multi-vendor marketplace for products and services.'
                );
                $canonical = $perPage['canonical'] ?? $request->fullUrl();
                $merged = array_merge([
                    'title'           => $defaultTitle,
                    'description'     => $defaultDesc,
                    'canonical'       => $canonical,
                    'site_name'       => $appName,
                    'locale'          => app()->getLocale(),
                    'og_type'         => 'website',
                    'og_image'        => null,
                    'twitter_card'    => 'summary_large_image',
                    'noindex'         => false,   // public pages default to indexable
                    'structured_data' => null,    // a single JSON-LD blob or array of blobs
                ], $perPage);
                // Final title pattern: "<page title> · <site name>" if page title differs.
                if (isset($perPage['title']) && $perPage['title'] !== $appName) {
                    $merged['title'] = $perPage['title'] . ' · ' . $appName;
                }
                return $merged;
            },

            // Phase 11B.3 v11B.3.1 §15 — canonical site settings shared with
            // every Inertia render. Grouped payload (branding, appearance,
            // footer, social, etc.) is fully locale-resolved server-side.
            // React components access `siteSettings.branding.site_name`
            // etc. via typed props — no per-component DB lookup.
            //
            // Defensive: if the settings service throws (fresh DB, cache
            // driver failure), fall back to config defaults so /login and
            // the storefront still render.
            'siteSettings' => (function () {
                try {
                    return app(\App\Services\Settings\SiteSettingsService::class)->publicPayload();
                } catch (\Throwable $e) {
                    \Log::warning('v11B.3.1 siteSettings share failed (defensive catch)', [
                        'error' => $e->getMessage(),
                    ]);
                    // Fall back to config defaults for the public groups only.
                    $public = ['branding', 'appearance', 'header', 'homepage', 'footer', 'contact', 'social', 'seo', 'mobile'];
                    $out = [];
                    foreach ($public as $g) {
                        $defaults = (array) config("site.defaults.{$g}", []);
                        $locale = app()->getLocale();
                        $out[$g] = array_map(function ($v) use ($locale) {
                            if (is_array($v) && array_key_exists('en', $v)) {
                                return $v[$locale] ?? $v['en'] ?? null;
                            }
                            return $v;
                        }, $defaults);
                    }
                    return $out;
                }
            })(),

            'auth' => [
                // Phase 10 v10.15 — defensive wrap. If any part of building
                // the auth.user array throws (Spatie permission cache out of
                // sync, vendor relation failure, etc.), the post-login render
                // must NOT crash. We log + return null. Inertia treats null
                // as "no auth.user" — pages requiring auth re-redirect to
                // login (recoverable UX) instead of returning a hard 500
                // (broken-feeling regression).
                'user' => function () use ($request) {
                    try {
                        $u = $request->user();
                        if (! $u) {
                            return null;
                        }
                        return [
                            'id'              => $u->id,
                            'name'            => $u->name,
                            'email'           => $u->email,
                            'email_verified'  => $u->hasVerifiedEmail(),
                            'roles'           => $u->getRoleNames()->toArray(),
                            // Phase 10 v10.11 §2 PERFORMANCE — `permissions` was
                            // previously `getAllPermissions()->pluck('name')->toArray()`
                            // which fired on EVERY Inertia request. For an admin user
                            // with the full permission catalogue (~80 rows post-Phase-7)
                            // this was a measurable per-request overhead (Spatie's
                            // direct + via-role permission query + pluck + array
                            // conversion). React pages overwhelmingly check
                            // `auth.user.is_admin` or `auth.user.roles`, not the
                            // full permissions list. v10.11 removes permissions
                            // from the default share. If a page needs them, it
                            // can request them via a partial reload using
                            // Inertia::partial / Inertia::lazy on a per-controller
                            // basis. is_admin and roles remain because they're
                            // cheap (single query each, Spatie-cached).
                            'is_admin'        => $u->hasAnyRole(['super_admin', 'admin_staff']),
                            // Phase 5 v6.1 — let the vendor layout hide menus
                            // (Wallet/Payouts/Reviews) for non-approved vendors.
                            // null = no vendor profile; 'approved' = full access.
                            'vendor_status'   => $u->vendor?->status,
                        ];
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning(
                            'auth.user share closure failed (Phase 10 v10.15 defensive catch)',
                            ['message' => $e->getMessage(), 'path' => $request->path()]
                        );
                        return null;
                    }
                },
            ],

            // Phase 10 v10.14 §4 PERFORMANCE — cart_summary is scope-aware.
            // Pre-v10.14 the closure fired for EVERY Inertia request including
            // admin and vendor pages (~10-20 page navigations per session).
            // Only StorefrontLayout reads `cart_summary` (verified: grep -r
            // cart_summary resources/js/ returns StorefrontLayout + the type
            // def only). For admin/vendor URLs we return null without
            // touching the cart relation — saves 1-2 SQL queries per
            // admin/vendor page navigation. The path check is constant-time.
            //
            // Phase 10 v10.15 — defensive wrapping. The dev's v10.14 report
            // indicated customer login was breaking, likely because some
            // shared-prop closure threw during the post-login redirect to /
            // (a 500 there reads as "login is broken"). Any exception inside
            // this closure now logs + returns null. The storefront cart
            // badge degrades to 0 silently; login + the homepage still
            // render. Authentication correctness > the cart-count badge.
            'cart_summary' => function () use ($request) {
                try {
                    $path = $request->path();
                    // Skip cart computation for admin/vendor surfaces — they
                    // don't render auth.cart_summary anywhere.
                    if (
                        $path === 'admin' || str_starts_with($path, 'admin/')
                        || $path === 'vendor' || str_starts_with($path, 'vendor/')
                        || $path === 'api' || str_starts_with($path, 'api/')
                    ) {
                        return null;
                    }
                    if (! $request->user()) {
                        // Phase 4 — guests don't have a cart yet
                        return null;
                    }
                    $cart = $request->user()->cart;
                    return [
                        'items_count'   => $cart?->items_count ?? 0,
                        'subtotal'      => number_format(($cart?->subtotal_minor ?? 0) / 100, 2),
                        'currency'      => $cart?->currency ?? config('marketplace.default_currency'),
                    ];
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'cart_summary share closure failed (Phase 10 v10.15 defensive catch)',
                        ['message' => $e->getMessage(), 'path' => $request->path()]
                    );
                    return null;
                }
            },

            // Phase 10 v10.6 — top categories shared globally so the
            // StorefrontLayout hamburger menu can render a collapsible
            // "Categories" section. Pre-v10.6 the categories rendered as
            // a separate <aside> in Catalog/Index.tsx — on mobile (1-col
            // grid) the aside appeared above the product grid, OUTSIDE
            // the hamburger drawer (the dev's "three categories outside
            // hamburger" report). v10.6 hides the aside on mobile and
            // serves the same list via this shared prop. 1-hour cache,
            // re-warmed by Phase 7 product mutations (existing cache
            // tags handle this).
            //
            // Phase 10 v10.14 §4 PERFORMANCE — scope-aware (same pattern as
            // cart_summary above). top_categories is read only by
            // StorefrontLayout; admin/vendor pages skip the cache lookup
            // entirely. On a cold cache, this also skips a categories
            // table scan for admin/vendor first-page-after-deploy.
            //
            // Phase 10 v10.15 — defensive wrapping. If the cache driver is
            // unreachable (e.g. CACHE_STORE=redis with Redis down) OR the
            // categories table query fails, return [] silently. The mobile
            // categories drawer falls back to empty; login + page render
            // still work. Authentication correctness > the category list.
            //
            // v11A.5: the cache stores name_translations alongside slug+name
            // (cache key bumped to v2 to invalidate the v1 English-only cache).
            // Locale resolution happens AFTER the cache lookup so a single
            // cache entry serves both English and Arabic responses.
            'top_categories' => function () use ($request) {
                try {
                    $path = $request->path();
                    if (
                        $path === 'admin' || str_starts_with($path, 'admin/')
                        || $path === 'vendor' || str_starts_with($path, 'vendor/')
                        || $path === 'api' || str_starts_with($path, 'api/')
                    ) {
                        return [];
                    }
                    $raw = \Illuminate\Support\Facades\Cache::remember(
                        'marketplace:top_categories:v2',
                        now()->addHour(),
                        function () {
                            if (! \Illuminate\Support\Facades\Schema::hasTable('categories')) {
                                return [];
                            }
                            return \App\Models\Category::query()
                                ->select(['slug', 'name', 'name_translations'])
                                ->where('is_active', true)
                                ->orderBy('position')
                                ->orderBy('name')
                                ->limit(50)
                                ->get()
                                ->map(fn ($c) => [
                                    'slug'               => $c->slug,
                                    'name'               => $c->name,
                                    'name_translations'  => $c->name_translations,
                                ])
                                ->all();
                        }
                    );
                    // Resolve to the active request locale (NOT cached per-locale)
                    $locale = app()->getLocale();
                    return collect($raw)->map(fn ($c) => [
                        'slug' => $c['slug'],
                        'name' => $c['name_translations'][$locale] ?? $c['name'],
                    ])->all();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'top_categories share closure failed (Phase 10 v10.15 defensive catch)',
                        ['message' => $e->getMessage(), 'path' => $request->path()]
                    );
                    return [];
                }
            },

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],

            // v3.3 — csrf_token removed from shared props. It's been a footgun:
            // its value can go stale on the client between renders and tempt
            // developers to read it as a static value. Inertia POSTs now rely
            // on the XSRF-TOKEN cookie (read live by axios on every request).
        ];
    }

    /**
     * Load the flat translation map for $locale.
     *
     * Falls back to English for any locale that doesn't exist on disk so
     * the page never breaks if a translation file is missing.
     *
     * @return array<string, string>
     */
    protected function loadTranslations(string $locale): array
    {
        // Phase 10 v10.1 PERFORMANCE — cache the merged translations
        // per-locale. Pre-v10.1 every request hit disk twice (en.json +
        // {locale}.json), decoded JSON, and array-merged — for a hot page
        // with ~50 req/s this was ~100 disk reads/s for static files.
        // Cache::rememberForever with a versioned key lets us invalidate
        // via cache:clear during deploys without manual coordination.
        //
        // Phase 10 v10.15 — defensive try/catch. Translations are read by
        // EVERY Inertia render. If the cache driver throws, fall back to
        // direct file reads so the login page (and post-login redirect)
        // still works.
        try {
            return \Illuminate\Support\Facades\Cache::remember(
                'inertia:translations:v1:' . $locale,
                now()->addHour(),
                fn () => $this->buildTranslationsForLocale($locale)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'translations cache failed (Phase 10 v10.15 defensive catch)',
                ['message' => $e->getMessage(), 'locale' => $locale]
            );
            return $this->buildTranslationsForLocale($locale);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function buildTranslationsForLocale(string $locale): array
    {
        $base = base_path('lang');
        $english = $this->readJson("{$base}/en.json");
        if ($locale === 'en') {
            return $english;
        }
        $localized = $this->readJson("{$base}/{$locale}.json");
        // English fallback wins for any key the localized file doesn't define
        return array_merge($english, $localized);
    }

    /**
     * @return array<string, string>
     */
    protected function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}
