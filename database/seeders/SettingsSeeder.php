<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── general ──────────────────────────────────────────
            ['general', 'site_name',          'Marketplace',                                      'string',  true,  null],
            ['general', 'site_tagline',       'Multi-vendor marketplace, dropshipping & services','string',  true,  null],
            ['general', 'support_email',      'support@marketplace.test',                          'string',  true,  null],
            ['general', 'support_phone',      '+965-0000-0000',                                    'string',  true,  null],
            ['general', 'timezone',           'Asia/Kuwait',                                       'string',  false, null],
            ['general', 'maintenance_mode',   false,                                               'boolean', false, 'When true, public storefront returns 503.'],

            // ── marketplace ──────────────────────────────────────
            ['marketplace', 'guest_browsing',           true,  'boolean', true,  'Guests can browse products/services.'],
            ['marketplace', 'guest_checkout',           false, 'boolean', true,  'Guests can complete checkout without login.'],
            ['marketplace', 'earnings_release_days',    7,     'integer', false, 'Days after delivery before vendor earnings become available.'],
            ['marketplace', 'require_email_verified',   true,  'boolean', false, 'Block checkout if email not verified.'],

            // ── currency ─────────────────────────────────────────
            ['currency', 'default',           'KWD',                       'string', true, null],
            ['currency', 'enabled',           ['KWD','USD','AED','PKR'],    'array',  true, null],

            // ── payment ──────────────────────────────────────────
            ['payment', 'methods_enabled',    ['card','cod','wallet'],     'array',  true, null],
            ['payment', 'cod_max_order',      50_000,                      'integer', false, 'Max order total in default-currency minor units that allows COD.'],

            // ── shipping ─────────────────────────────────────────
            ['shipping', 'free_shipping_threshold', 25_000,                'integer', true, 'Free shipping above this amount in minor units of default currency.'],
            ['shipping', 'default_delivery_days',   3,                     'integer', true, null],

            // ── commission ───────────────────────────────────────
            ['commission', 'default_basic_percent',        30, 'integer', false, null],
            ['commission', 'default_standard_percent',     20, 'integer', false, null],
            ['commission', 'default_professional_percent', 10, 'integer', false, null],
            ['commission', 'default_calculation_base',     'selling_price', 'string', false, 'selling_price | net_profit_after_cost'],

            // ── email ────────────────────────────────────────────
            ['email', 'from_address',         'no-reply@marketplace.test',  'string', false, null],
            ['email', 'from_name',            'Marketplace',                'string', false, null],
            ['email', 'reply_to',             'support@marketplace.test',   'string', false, null],

            // ── seo ──────────────────────────────────────────────
            ['seo', 'meta_title',             'Marketplace — Buy, sell, book',   'string', true, null],
            ['seo', 'meta_description',       'Multi-vendor marketplace.',       'string', true, null],
            ['seo', 'og_image_path',          null,                              'string', true, null],

            // ── social ───────────────────────────────────────────
            ['social', 'facebook_url',        null, 'string', true, null],
            ['social', 'instagram_url',       null, 'string', true, null],
            ['social', 'twitter_url',         null, 'string', true, null],
            ['social', 'youtube_url',         null, 'string', true, null],

            // ── security ─────────────────────────────────────────
            ['security', 'two_factor_required_for_admin', false, 'boolean', false, null],
            ['security', 'session_timeout_minutes',       120,   'integer', false, null],
            ['security', 'password_min_length',           8,     'integer', false, null],
            ['security', 'max_login_attempts',            5,     'integer', false, null],
        ];

        foreach ($settings as [$group, $key, $value, $type, $isPublic, $description]) {
            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'value'        => Setting::wrap($value),
                    'type'         => $type,
                    'is_encrypted' => false,
                    'is_public'    => $isPublic,
                    'description'  => $description,
                ],
            );
        }

        $this->command?->info(sprintf('Seeded %d settings across 10 groups.', count($settings)));
    }
}
