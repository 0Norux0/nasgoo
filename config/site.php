<?php

declare(strict_types=1);

/**
 * Phase 11B.3 v11B.3.1 — default site settings.
 *
 * The SiteSettingsService reads DB rows FIRST, then falls back to this file
 * for missing keys. When an admin saves a value in the DB, that overrides
 * the default — no code edit needed.
 *
 * Translatable values are arrays `['en' => '...', 'ar' => '...']`.
 * The service resolves them to the active locale for storefront display.
 */
return [

    'defaults' => [

        // ─── §5 Branding ────────────────────────────────────────────
        'branding' => [
            'site_name'         => env('APP_NAME', 'Marketplace'),
            'short_name'        => 'Market',
            'legal_name'        => env('APP_NAME', 'Marketplace'),
            'tagline'           => ['en' => 'Discover more', 'ar' => 'اكتشف المزيد'],
            'logo_url'          => '/images/logo.svg',
            'logo_dark_url'     => '/images/logo-dark.svg',
            'logo_compact_url'  => '/images/logo-compact.svg',
            'favicon_url'       => '/favicon.ico',
            'social_image_url'  => '/images/og-default.png',
            'email_logo_url'    => '/images/logo.svg',
        ],

        // ─── §6 Appearance (theme tokens) ───────────────────────────
        // CSS custom properties are injected server-side into the <html>
        // element so a page reload picks up admin color changes without
        // rebuilding Tailwind.
        'appearance' => [
            'color_primary'            => '#4f46e5',
            'color_primary_foreground' => '#ffffff',
            'color_secondary'          => '#8b5cf6',
            'color_accent'             => '#f59e0b',
            'color_success'            => '#10b981',
            'color_warning'            => '#f59e0b',
            'color_danger'             => '#ef4444',
            'color_surface'            => '#ffffff',
            'color_background'         => '#f8fafc',
            'color_text'               => '#0f172a',
            'color_muted'              => '#64748b',
            'color_border'             => '#e2e8f0',
            'color_link'               => '#4f46e5',
            'browser_theme_color'      => '#4f46e5',
        ],

        // ─── §9 Header ─────────────────────────────────────────────
        'header' => [
            'announcement_enabled' => false,
            'announcement_text'    => ['en' => '', 'ar' => ''],
            'announcement_url'     => '',
            'main_nav'             => [
                // A list of items; each { url, label:{en,ar}, order, visibility }
            ],
            'contact_link_enabled' => true,
        ],

        // ─── §7 Homepage section ordering + enablement ─────────────
        // The list of section keys in display order. HomepageSectionRegistry
        // renders exactly these, in this order, skipping any disabled.
        'homepage' => [
            'section_order' => ['hero', 'trust', 'personalization', 'categories', 'featured', 'services'],
            'sections'      => [
                'hero'            => [
                    'enabled' => true,
                    'heading' => ['en' => 'Welcome', 'ar' => 'مرحباً'],
                    'subheading' => ['en' => '', 'ar' => ''],
                    'image_url' => '',
                    'cta_url' => '/products',
                    'cta_label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
                ],
                'trust'           => ['enabled' => true],
                'personalization' => ['enabled' => true],
                'categories'      => ['enabled' => true, 'limit' => 12],
                'featured'        => ['enabled' => true, 'limit' => 8],
                'services'        => ['enabled' => false, 'limit' => 6],
                'newsletter'      => ['enabled' => false],
                'custom_banner'   => ['enabled' => false, 'image_url' => '', 'cta_url' => ''],
            ],
        ],

        // ─── §10 Footer ────────────────────────────────────────────
        'footer' => [
            'description' => ['en' => 'A trusted multi-vendor marketplace.',
                              'ar' => 'سوق متعدد البائعين موثوق.'],
            'copyright'   => ['en' => '© Marketplace. All rights reserved.',
                              'ar' => '© جميع الحقوق محفوظة.'],
            'columns'     => [
                [
                    'heading' => ['en' => 'Shop', 'ar' => 'تسوق'],
                    'links'   => [
                        ['url' => '/products',  'label' => ['en' => 'All Products', 'ar' => 'كل المنتجات']],
                        ['url' => '/services',  'label' => ['en' => 'Services',      'ar' => 'الخدمات']],
                    ],
                ],
                [
                    'heading' => ['en' => 'Support', 'ar' => 'الدعم'],
                    'links'   => [
                        ['url' => '/tickets', 'label' => ['en' => 'Help center', 'ar' => 'مركز المساعدة']],
                    ],
                ],
            ],
            'legal_links' => [
                ['url' => '/privacy',       'label' => ['en' => 'Privacy',        'ar' => 'الخصوصية']],
                ['url' => '/terms',         'label' => ['en' => 'Terms',          'ar' => 'الشروط']],
            ],
        ],

        // ─── Contact ──────────────────────────────────────────────
        'contact' => [
            'email'    => env('MAIL_FROM_ADDRESS', 'support@example.test'),
            'phone'    => '',
            'whatsapp' => '',
            'address'  => ['en' => '', 'ar' => ''],
        ],

        // ─── §11 Social links ──────────────────────────────────────
        'social' => [
            'facebook'  => '',
            'instagram' => '',
            'tiktok'    => '',
            'youtube'   => '',
            'linkedin'  => '',
            'twitter'   => '',
            'whatsapp'  => '',
            'telegram'  => '',
            'snapchat'  => '',
        ],

        // ─── §12 SEO defaults ──────────────────────────────────────
        'seo' => [
            'default_title'        => ['en' => 'Marketplace', 'ar' => 'المتجر'],
            'title_suffix'         => ['en' => ' — Marketplace', 'ar' => ' — المتجر'],
            'default_description'  => ['en' => 'Trusted multi-vendor marketplace.',
                                       'ar' => 'سوق متعدد البائعين موثوق.'],
            'default_og_image'     => '/images/og-default.png',
            'canonical_base_url'   => env('APP_URL', ''),
            'organization_name'    => env('APP_NAME', 'Marketplace'),
        ],

        // ─── Mobile-specific tweaks ─────────────────────────────────
        'mobile' => [
            'sticky_cta_enabled'   => true,
            'bottom_nav_enabled'   => false,   // reserved for future PWA nav
            'show_promotion_bar'   => true,
        ],

        // ─── §20 Vendor intelligence thresholds ─────────────────────
        // Admin-tunable via SiteSettingsService — no code edits needed
        // to change how aggressive the vendor-intelligence engine is.
        'vendor_intelligence' => [
            'enabled'                    => true,
            'scheduler_enabled'          => true,    // v11B.4.3 — expose to admin
            'low_stock_threshold'        => 5,       // units
            'fast_moving_days'           => 30,      // orders in last N days = "fast"
            'fast_moving_min_orders'     => 5,       // ≥ N completed orders in window
            'slow_moving_days'           => 60,      // no orders for ≥ N days
            'slow_moving_min_age_days'   => 30,      // product must exist ≥ N days before "slow"
            'stagnant_days'              => 60,      // no stock movement for ≥ N days
            'min_views_for_conversion'   => 100,     // §11 min evidence for HVLC alert
            'high_view_conversion_ceil'  => 0.01,    // conversion < 1% flagged
            'min_wishlist_interest'      => 10,      // §11 wishlist_adds ≥ N
            'min_cart_abandonment'       => 10,      // §11 cart_adds - purchases ≥ N
            'dashboard_alert_limit'      => 10,      // §8 don't overwhelm
            'default_snooze_days'        => 7,
            'cache_ttl'                  => 900,     // v11B.4.3 — dashboard cache TTL in seconds
            // v11B.4.3 email digest thresholds/switches (see Fix 2)
            'digest_emails_enabled'      => false,   // MASTER opt-in; default OFF for safety
            'digest_min_critical'        => 1,       // send digest only when critical ≥ N
            'digest_throttle_hours'      => 24,      // no more than one per vendor per N hours
            'quality_weights' => [                    // §30 must sum to 100
                'core'      => 30,
                'media'     => 20,
                'i18n'      => 20,
                'inventory' => 15,
                'seo'       => 10,
                'policy'    => 5,
            ],
        ],
    ],
];
