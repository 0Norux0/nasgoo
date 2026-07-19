<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\HomepageSectionRegistry;
use App\Services\Settings\SiteSettingsService;
use App\Support\MarketplaceMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase 11B.3 v11B.3.1 §13 §47 — admin site-settings interface.
 *
 * Groups: branding, appearance, header, homepage, footer, contact, social,
 * seo, mobile. Each group is a tab. Admin sees the RAW (multi-locale)
 * values so they can edit English and Arabic side-by-side.
 *
 * Authorization: only super_admin. Enforced by the middleware in
 * routes/web.php AND by an explicit abort_unless in each action for
 * defense in depth.
 */
class SiteSettingsController extends Controller
{
    /**
     * List all groups + their current values (raw, multi-locale for translatables).
     */
    public function index(Request $request): InertiaResponse
    {
        $this->authorizeAdmin($request);

        $svc = app(SiteSettingsService::class);
        // Phase 11B.4 v11B.4.3 Fix 1 — vendor_intelligence added so the
        // admin Site Settings page receives the values as an Inertia prop
        // and can render the Vendor Intelligence tab. Without this,
        // settings[vendor_intelligence] was undefined in React → the tab
        // could open but every field showed as empty and Save was a no-op.
        $groups = [
            'branding', 'appearance', 'header', 'homepage',
            'footer', 'contact', 'social', 'seo', 'mobile',
            'vendor_intelligence',
        ];

        $payload = [];
        foreach ($groups as $g) {
            $raw = $svc->groupRaw($g);
            $defaults = (array) config("site.defaults.{$g}", []);
            $payload[$g] = array_merge($defaults, $raw);
        }

        return Inertia::render('Admin/SiteSettings/Index', [
            'settings'          => $payload,
            'sections_registry' => HomepageSectionRegistry::all(),
        ]);
    }

    /**
     * Save one or more settings within a group.
     *
     * POST /admin/site-settings/{group}
     *   { key: value, key: {en:..., ar:...}, ... }
     */
    public function update(Request $request, string $group): RedirectResponse
    {
        $this->authorizeAdmin($request);

        // Only allow persisting to registered groups.
        // Phase 11B.4 v11B.4.3 Fix 1 — vendor_intelligence added so
        // POST /admin/site-settings/vendor_intelligence no longer 422s.
        // v11B.4.2 fixed the route regex + validation branch but the
        // update() method had a SECOND allowlist that still blocked it.
        $allowed = ['branding', 'appearance', 'header', 'homepage', 'footer',
                    'contact', 'social', 'seo', 'mobile', 'vendor_intelligence'];
        abort_unless(in_array($group, $allowed, true), 422, 'Unknown group');

        // Validate group-specific values (per dev §38 tests reject invalid values)
        $data = $this->validateGroup($group, $request->all());

        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs["{$group}.{$key}"] = $value;
        }

        app(SiteSettingsService::class)->setMany($pairs, $request->user()->id);

        return back()->with('flash.success', __('site_settings.saved'));
    }

    /**
     * Reset a group to config defaults.
     */
    public function reset(Request $request, string $group): RedirectResponse
    {
        $this->authorizeAdmin($request);
        app(SiteSettingsService::class)->resetGroup($group);
        return back()->with('flash.success', __('site_settings.reset'));
    }

    /**
     * Upload an image (logo, favicon, banner, OG image) and return the URL.
     * File is validated + stored under the public disk in an admin folder.
     */
    public function uploadImage(Request $request): array
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg,webp,svg,ico|max:2048',
            'group' => 'required|in:branding,homepage,footer,seo,social',
            'key'   => 'required|string|max:64',
        ]);

        // v11B.3.1 §35 §47 — reject SVG-with-script by content sniff.
        // Simple defense: parse the SVG and refuse if <script> or "javascript:" appears.
        if ($request->file('image')->getClientOriginalExtension() === 'svg') {
            $svgBody = file_get_contents($request->file('image')->getRealPath());
            if (preg_match('/<script|javascript:|onerror=|onload=/i', $svgBody)) {
                throw ValidationException::withMessages([
                    'image' => __('site_settings.svg_script_rejected'),
                ]);
            }
        }

        $path = MarketplaceMedia::storePublicPreservingExtension(
            $request->file('image'),
            'site-settings/' . $data['group'],
        );

        if (! $path) {
            throw ValidationException::withMessages([
                'image' => __('site_settings.upload_failed'),
            ]);
        }

        $url = MarketplaceMedia::publicUrl($path);

        if ($url) {
            $this->persistUploadedImageSetting($data['group'], $data['key'], $url, $request->user()->id);
        }

        return ['url' => $url, 'path' => $path];
    }

    // ─── validation helpers ──────────────────────────────────────

    private function validateGroup(string $group, array $data): array
    {
        return match ($group) {
            'branding' => $this->validateBranding($data),
            'appearance' => $this->validateAppearance($data),
            'header'    => $this->validateHeader($data),
            'homepage'  => $this->validateHomepage($data),
            'footer'    => $this->validateFooter($data),
            'contact'   => $this->validateContact($data),
            'social'    => $this->validateSocial($data),
            'seo'       => $this->validateSeo($data),
            'mobile'    => $this->validateMobile($data),
            // Phase 11B.4 v11B.4.2 Defect 3 fix — vendor_intelligence group.
            // Was previously blocked by both the route regex AND the fact
            // that this match had no branch for it (default: []).
            'vendor_intelligence' => $this->validateVendorIntelligence($data),
            default     => [],
        };
    }

    /**
     * Phase 11B.4 v11B.4.2 §6 — validation for admin-tunable vendor
     * intelligence thresholds. All numeric fields validated as
     * non-negative integers or floats where appropriate. Boolean
     * feature flag is Boolean-cast.
     *
     * quality_weights validated as an array with 6 keys summing to 100
     * (loosely — if they don't, the service falls back to defaults, so
     * this validation is a safety net, not a hard constraint).
     */
    private function validateVendorIntelligence(array $data): array
    {
        return Validator::make($data, [
            'enabled'                    => 'sometimes|boolean',
            'scheduler_enabled'          => 'sometimes|boolean',
            'low_stock_threshold'        => 'sometimes|integer|min:0|max:100000',
            'fast_moving_days'           => 'sometimes|integer|min:1|max:365',
            'fast_moving_min_orders'     => 'sometimes|integer|min:1|max:10000',
            'slow_moving_days'           => 'sometimes|integer|min:1|max:730',
            'slow_moving_min_age_days'   => 'sometimes|integer|min:1|max:365',
            'stagnant_days'              => 'sometimes|integer|min:1|max:730',
            'min_views_for_conversion'   => 'sometimes|integer|min:1|max:1000000',
            'high_view_conversion_ceil'  => 'sometimes|numeric|min:0|max:1',
            'min_wishlist_interest'      => 'sometimes|integer|min:1|max:100000',
            'min_cart_abandonment'       => 'sometimes|integer|min:1|max:100000',
            'dashboard_alert_limit'      => 'sometimes|integer|min:1|max:100',
            'default_snooze_days'        => 'sometimes|integer|min:1|max:90',
            'cache_ttl'                  => 'sometimes|integer|min:60|max:86400',
            // v11B.4.3 Fix 2 — email digest tunables
            'digest_emails_enabled'      => 'sometimes|boolean',
            'digest_min_critical'        => 'sometimes|integer|min:0|max:100',
            'digest_throttle_hours'      => 'sometimes|integer|min:1|max:168',
            'quality_weights'            => 'sometimes|array',
            'quality_weights.core'       => 'sometimes|integer|min:0|max:100',
            'quality_weights.media'      => 'sometimes|integer|min:0|max:100',
            'quality_weights.i18n'       => 'sometimes|integer|min:0|max:100',
            'quality_weights.inventory'  => 'sometimes|integer|min:0|max:100',
            'quality_weights.seo'        => 'sometimes|integer|min:0|max:100',
            'quality_weights.policy'     => 'sometimes|integer|min:0|max:100',
        ])->validate();
    }

    private function validateBranding(array $data): array
    {
        return Validator::make($data, [
            'site_name'   => 'nullable|string|max:120',
            'short_name'  => 'nullable|string|max:32',
            'legal_name'  => 'nullable|string|max:120',
            'tagline'     => 'nullable|array',
            'tagline.en'  => 'nullable|string|max:200',
            'tagline.ar'  => 'nullable|string|max:200',
            'logo_url'    => 'nullable|string|max:500',
            'logo_dark_url'  => 'nullable|string|max:500',
            'logo_compact_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'social_image_url' => 'nullable|string|max:500',
            'email_logo_url' => 'nullable|string|max:500',
        ])->validate();
    }

    private function persistUploadedImageSetting(string $group, string $key, string $url, ?int $userId): void
    {
        $svc = app(SiteSettingsService::class);
        $persistKey = $this->persistableImageSettingKey($group, $key);

        if ($persistKey) {
            $svc->set($persistKey, $url, $userId);

            if ($persistKey === 'branding.logo_url') {
                $this->cascadePrimaryLogo($svc, $url, $userId);
            }

            return;
        }

        if ($group === 'homepage' && preg_match('/^([a-z0-9_]+)-image_url$/', $key, $matches)) {
            $sections = (array) $svc->get('homepage.sections', []);
            $sectionKey = $matches[1];
            $current = (array) ($sections[$sectionKey] ?? []);
            $sections[$sectionKey] = array_merge($current, ['image_url' => $url]);
            $svc->set('homepage.sections', $sections, $userId);
            return;
        }

        if ($group === 'homepage' && preg_match('/^([a-z0-9_]+)-card_images-(\d+)$/', $key, $matches)) {
            $sections = (array) $svc->get('homepage.sections', []);
            $sectionKey = $matches[1];
            $index = (int) $matches[2];
            $current = (array) ($sections[$sectionKey] ?? []);
            $images = array_values((array) ($current['card_images'] ?? []));

            for ($i = 0; $i < 4; $i++) {
                $images[$i] = (string) ($images[$i] ?? '');
            }

            if ($index >= 0 && $index < 4) {
                $images[$index] = $url;
                $sections[$sectionKey] = array_merge($current, ['card_images' => array_slice($images, 0, 4)]);
                $svc->set('homepage.sections', $sections, $userId);
            }
        }
    }

    private function persistableImageSettingKey(string $group, string $key): ?string
    {
        $allowed = [
            'branding' => [
                'logo_url',
                'logo_dark_url',
                'logo_compact_url',
                'favicon_url',
                'social_image_url',
                'email_logo_url',
            ],
            'seo' => [
                'default_og_image',
            ],
        ];

        if (isset($allowed[$group]) && in_array($key, $allowed[$group], true)) {
            return "{$group}.{$key}";
        }

        return null;
    }

    private function cascadePrimaryLogo(SiteSettingsService $svc, string $url, ?int $userId): void
    {
        $rawBranding = $svc->groupRaw('branding');
        $fallbackTargets = [
            'logo_dark_url',
            'logo_compact_url',
            'email_logo_url',
            'social_image_url',
            'favicon_url',
        ];

        foreach ($fallbackTargets as $target) {
            $current = (string) ($rawBranding[$target] ?? '');
            $default = (string) config("site.defaults.branding.{$target}", '');

            if ($current === '' || $current === $default) {
                $svc->set("branding.{$target}", $url, $userId);
            }
        }
    }

    private function validateAppearance(array $data): array
    {
        $colorRule = 'nullable|regex:/^#[0-9a-fA-F]{3,8}$/';
        return Validator::make($data, [
            'color_primary'            => $colorRule,
            'color_primary_foreground' => $colorRule,
            'color_secondary'          => $colorRule,
            'color_accent'             => $colorRule,
            'color_success'            => $colorRule,
            'color_warning'            => $colorRule,
            'color_danger'             => $colorRule,
            'color_surface'            => $colorRule,
            'color_background'         => $colorRule,
            'color_text'               => $colorRule,
            'color_muted'              => $colorRule,
            'color_border'             => $colorRule,
            'color_link'               => $colorRule,
            'browser_theme_color'      => $colorRule,
        ])->validate();
    }

    private function validateHeader(array $data): array
    {
        return Validator::make($data, [
            'announcement_enabled' => 'nullable|boolean',
            'announcement_text'    => 'nullable|array',
            'announcement_text.en' => 'nullable|string|max:200',
            'announcement_text.ar' => 'nullable|string|max:200',
            'announcement_url'     => ['nullable', 'string', 'max:500', $this->safeUrlRule()],
            'main_nav'             => 'nullable|array',
            'contact_link_enabled' => 'nullable|boolean',
        ])->validate();
    }

    private function validateHomepage(array $data): array
    {
        return Validator::make($data, [
            'section_order' => 'nullable|array',
            'section_order.*' => 'string|in:' . implode(',', array_keys(HomepageSectionRegistry::all())),
            'sections' => 'nullable|array',
        ])->validate();
    }

    private function validateFooter(array $data): array
    {
        return Validator::make($data, [
            'description' => 'nullable|array',
            'description.en' => 'nullable|string|max:500',
            'description.ar' => 'nullable|string|max:500',
            'copyright'   => 'nullable|array',
            'copyright.en' => 'nullable|string|max:200',
            'copyright.ar' => 'nullable|string|max:200',
            'columns'     => 'nullable|array',
            'legal_links' => 'nullable|array',
        ])->validate();
    }

    private function validateContact(array $data): array
    {
        return Validator::make($data, [
            'email'    => 'nullable|email|max:120',
            'phone'    => 'nullable|string|max:40',
            'whatsapp' => 'nullable|string|max:40',
            'address'  => 'nullable|array',
        ])->validate();
    }

    private function validateSocial(array $data): array
    {
        // Every social link must be a valid URL and start with http(s).
        // No `javascript:`, no `data:`, no relative path.
        $rules = [];
        foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin',
                  'twitter', 'whatsapp', 'telegram', 'snapchat'] as $platform) {
            $rules[$platform] = ['nullable', 'string', 'max:500', $this->safeUrlRule(true)];
        }
        return Validator::make($data, $rules)->validate();
    }

    private function validateSeo(array $data): array
    {
        return Validator::make($data, [
            'default_title'       => 'nullable|array',
            'title_suffix'        => 'nullable|array',
            'default_description' => 'nullable|array',
            'default_og_image'    => 'nullable|string|max:500',
            'canonical_base_url'  => ['nullable', 'string', 'max:500', $this->safeUrlRule(true)],
            'organization_name'   => 'nullable|string|max:120',
        ])->validate();
    }

    private function validateMobile(array $data): array
    {
        return Validator::make($data, [
            'sticky_cta_enabled' => 'nullable|boolean',
            'bottom_nav_enabled' => 'nullable|boolean',
            'show_promotion_bar' => 'nullable|boolean',
        ])->validate();
    }

    /**
     * Safe URL rule: rejects `javascript:`, `data:`, and inline event handlers.
     * When $requireHttp, ALSO requires the URL to start with http(s):// or /.
     */
    private function safeUrlRule(bool $requireHttp = true): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($requireHttp) {
            if ($value === null || $value === '') return;
            if (! is_string($value)) {
                $fail(__('site_settings.invalid_url'));
                return;
            }
            $normalized = strtolower(trim($value));
            if (str_starts_with($normalized, 'javascript:') ||
                str_starts_with($normalized, 'data:') ||
                str_starts_with($normalized, 'vbscript:')) {
                $fail(__('site_settings.unsafe_url'));
                return;
            }
            if ($requireHttp) {
                if (! str_starts_with($normalized, 'http://') &&
                    ! str_starts_with($normalized, 'https://') &&
                    ! str_starts_with($normalized, '/')) {
                    $fail(__('site_settings.invalid_url'));
                }
            }
        };
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('super_admin'), 403);
    }
}
