<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * Phase 11B.3 v11B.3.1 §8 §33 — homepage section registry.
 *
 * Central declaration of every supported homepage section. Each section
 * defines:
 *   - key: stable identifier used in `site.defaults.homepage.section_order`
 *   - component: React component to render (matches Welcome.tsx switch)
 *   - default_settings: baseline config
 *   - required_feature: feature flag guard (settings evaluated first;
 *                       when the flag is off the section is dropped even
 *                       if enabled=true)
 *
 * Do NOT let admin config create sections that aren't registered here.
 * Do NOT allow arbitrary component names. Do NOT allow raw HTML.
 */
class HomepageSectionRegistry
{
    /**
     * @return array<string, array{
     *   key: string,
     *   component: string,
     *   default_settings: array<string, mixed>,
     *   required_feature: ?string,
     *   allows_translation: bool,
     * }>
     */
    public static function all(): array
    {
        return [
            'hero' => [
                'key'                => 'hero',
                'component'          => 'Hero',
                'default_settings'   => [
                    'enabled' => true,
                    'heading' => ['en' => 'Welcome', 'ar' => 'مرحباً'],
                    'subheading' => ['en' => '', 'ar' => ''],
                    'image_url' => '',
                    'card_images' => ['', '', '', ''],
                    'cta_url' => '/products',
                    'cta_label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
                ],
                'required_feature'   => null,
                'allows_translation' => true,
            ],
            'trust' => [
                'key'                => 'trust',
                'component'          => 'TrustIndicators',
                'default_settings'   => ['enabled' => true],
                'required_feature'   => null,
                'allows_translation' => false,
            ],
            'personalization' => [
                'key'                => 'personalization',
                'component'          => 'PersonalizedSections',
                'default_settings'   => ['enabled' => true],
                'required_feature'   => 'marketplace_personalization.features.enabled',
                'allows_translation' => false,
            ],
            'categories' => [
                'key'                => 'categories',
                'component'          => 'FeaturedCategories',
                'default_settings'   => ['enabled' => true, 'limit' => 12],
                'required_feature'   => null,
                'allows_translation' => false,
            ],
            'featured' => [
                'key'                => 'featured',
                'component'          => 'FeaturedProducts',
                'default_settings'   => ['enabled' => true, 'limit' => 8],
                'required_feature'   => null,
                'allows_translation' => false,
            ],
            'services' => [
                'key'                => 'services',
                'component'          => 'FeaturedServices',
                'default_settings'   => ['enabled' => false, 'limit' => 6],
                'required_feature'   => null,
                'allows_translation' => false,
            ],
            'newsletter' => [
                'key'                => 'newsletter',
                'component'          => 'NewsletterSection',
                'default_settings'   => ['enabled' => false],
                'required_feature'   => null,
                'allows_translation' => true,
            ],
            'custom_banner' => [
                'key'                => 'custom_banner',
                'component'          => 'CustomBanner',
                'default_settings'   => [
                    'enabled' => false, 'image_url' => '', 'cta_url' => '',
                    'heading' => ['en' => '', 'ar' => ''],
                ],
                'required_feature'   => null,
                'allows_translation' => true,
            ],
        ];
    }

    /**
     * Resolve which sections should render in what order, given the current
     * settings. Returns a list of section descriptors, order preserved,
     * with disabled sections + unknown keys dropped.
     *
     * @return array<int, array{key:string, component:string, settings:array<string,mixed>}>
     */
    public static function resolve(SiteSettingsService $settings): array
    {
        $registered = self::all();
        $order      = (array) $settings->get('homepage.section_order', array_keys($registered));
        $configured = (array) $settings->get('homepage.sections', []);

        $out = [];
        foreach ($order as $key) {
            // Unknown keys silently dropped (dev §39.25 "unknown section uses safe fallback")
            if (! isset($registered[$key])) continue;

            $reg = $registered[$key];
            // Merge configured settings with registry defaults; DB wins
            $s = array_merge($reg['default_settings'], (array) ($configured[$key] ?? []));

            // Enabled check
            if (! (bool) ($s['enabled'] ?? true)) continue;

            // Required feature flag guard (dev §8)
            if ($reg['required_feature'] && ! config($reg['required_feature'], true)) continue;

            $out[] = [
                'key'       => $key,
                'component' => $reg['component'],
                'settings'  => $s,
            ];
        }
        return $out;
    }
}
