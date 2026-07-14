<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marketplace Configuration
|--------------------------------------------------------------------------
|
| Central config for marketplace-wide defaults. Many of these values are
| ALSO available as runtime-editable rows in the `settings` table (added
| in Phase 1) so admin can change them from the panel without a deploy.
|
| This file holds boot-time defaults and feature flags.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */
    'supported_locales' => explode(',', env('SUPPORTED_LOCALES', 'en,ar,ur')),

    'locale_metadata' => [
        'en' => ['name' => 'English',    'native' => 'English',  'direction' => 'ltr'],
        'ar' => ['name' => 'Arabic',     'native' => 'العربية',  'direction' => 'rtl'],
        'ur' => ['name' => 'Urdu',       'native' => 'اردو',      'direction' => 'rtl'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    | KWD is the launch default per the approved plan. Admin can change
    | the default via the settings panel (Phase 1).
    */
    'default_currency'     => env('DEFAULT_CURRENCY', 'KWD'),
    'supported_currencies' => explode(',', env('SUPPORTED_CURRENCIES', 'KWD,USD,AED,PKR')),

    'currency_metadata' => [
        'KWD' => ['name' => 'Kuwaiti Dinar',      'symbol' => 'KD',  'minor_units' => 3],
        'USD' => ['name' => 'US Dollar',          'symbol' => '$',   'minor_units' => 2],
        'AED' => ['name' => 'UAE Dirham',         'symbol' => 'AED', 'minor_units' => 2],
        'PKR' => ['name' => 'Pakistani Rupee',    'symbol' => '₨',   'minor_units' => 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest access (decision #10)
    |--------------------------------------------------------------------------
    */
    'guest_browsing' => (bool) env('MARKETPLACE_GUEST_BROWSING', true),
    'guest_checkout' => (bool) env('MARKETPLACE_GUEST_CHECKOUT', false),

    /*
    |--------------------------------------------------------------------------
    | Vendor commission defaults (decision #6)
    |--------------------------------------------------------------------------
    | These seed values are inserted into the `vendor_packages` table in
    | Phase 2. Admin retains full per-vendor / per-category / per-product
    | override capability via `vendor_commission_rules`.
    */
    'default_commissions' => [
        'basic'        => (float) env('MARKETPLACE_DEFAULT_COMMISSION_BASIC', 30),
        'standard'     => (float) env('MARKETPLACE_DEFAULT_COMMISSION_STANDARD', 20),
        'professional' => (float) env('MARKETPLACE_DEFAULT_COMMISSION_PROFESSIONAL', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vendor earnings release window (decision #11)
    |--------------------------------------------------------------------------
    | After an order is marked delivered (or a booking is completed),
    | vendor earnings sit in `balance_pending` for this many days before
    | moving to `balance_available`. Admin-overridable from settings.
    */
    'earnings_release_days' => (int) env('MARKETPLACE_DEFAULT_RETURN_WINDOW_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Feature flags (toggles that admin will eventually control)
    |--------------------------------------------------------------------------
    */
    'features' => [
        'dropshipping'           => true,
        'customization'          => true,
        'services'               => true,
        'wallet'                 => true,
        'promotions'             => true,
        'deal_of_the_day'        => true,
        'flash_sales'            => true,
        'multi_language'         => true,
        'multi_currency'         => true,
        'two_factor_auth'        => false, // enable in Phase 1
        'sms_notifications'      => false, // Phase 11
        'whatsapp_notifications' => false, // Phase 11
        'push_notifications'     => false, // Phase 11
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier platforms (decision #13 — manual + CSV only at MVP)
    |--------------------------------------------------------------------------
    | Adapter classes are stubbed in Phase 6. Listed here for reference.
    */
    'supplier_platforms_enabled_at_mvp' => [
        'manual',
        'csv',
    ],

    'supplier_platforms_prepared_for_later' => [
        'aliexpress',
        'daraz',
        'amazon',
        'temu',
        'alibaba',
        'printful',
        'printify',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media disk (product images, vendor logos)
    |--------------------------------------------------------------------------
    | 'public' for local/dev (served via storage:link). Set MEDIA_DISK=s3 in
    | production to use Cloudflare R2 / MinIO (see config/filesystems.php).
    */
    'media_disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Vendor private documents disk (Phase 10 v10.6)
    |--------------------------------------------------------------------------
    | Disk that stores vendor application documents (license, ID). NEVER
    | exposed via storage:link — reads only happen through the signed
    | VendorFileController route. Defaults to 'vendors' which is configured
    | in config/filesystems.php as a local disk rooted at
    | storage/app/private. Override via VENDOR_PRIVATE_DISK if a site
    | prefers S3 or another configured disk.
    */
    'vendor_private_disk' => env('VENDOR_PRIVATE_DISK', 'vendors'),

    /*
    |--------------------------------------------------------------------------
    | Vendor public assets disk (Phase 10 v10.7)
    |--------------------------------------------------------------------------
    | Disk that stores vendor PUBLIC assets — logo + banner. Files on this
    | disk are exposed at /storage/... via `php artisan storage:link`.
    | Pre-v10.7 these uploads landed on the default disk (= 'local'), but
    | the preview logic read from 'public' — producing "File not found" for
    | every logo/banner image. v10.7 routes new uploads here; legacy
    | records on other disks are still resolvable via VendorFileResolver's
    | fallback search (served through the signed admin route).
    */
    'vendor_public_disk' => env('VENDOR_PUBLIC_DISK', 'public'),

];
