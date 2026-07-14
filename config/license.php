<?php

/*
|--------------------------------------------------------------------------
| License activation configuration (Phase 12.3)
|--------------------------------------------------------------------------
|
| Ownership-protection layer using Ed25519 signature verification.
| The private signing key MUST stay with the license owner (Aamir) and
| MUST NEVER appear in this codebase, this config, or `.env`.
|
| This file references only the PUBLIC verification key + operational
| toggles. See docs/LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md for the
| owner's key-generation procedure, and tools/license-generator/ for
| the offline token generator.
|
| Every value below can be overridden via .env — see
| .env.example.production for the LICENSE_* variables.
*/

return [

    /*
    |--------------------------------------------------------------------
    | Enforcement toggle
    |--------------------------------------------------------------------
    |
    | When false, the license middleware still LOADS but immediately
    | passes every request through. Set to true only after:
    |   - A production public key is installed (see below)
    |   - The owner has generated a valid license token
    |   - The migration `2027_02_01_000001_create_license_tables.php`
    |     has been run
    |
    | Local development typically leaves this false so no gating occurs.
    */
    'enforcement_enabled' => env('LICENSE_ENFORCEMENT_ENABLED', false),

    /*
    |--------------------------------------------------------------------
    | Ed25519 public verification key
    |--------------------------------------------------------------------
    |
    | 32-byte raw Ed25519 public key, base64-encoded.
    | The paired private key is held by the owner and is NEVER in this
    | repository, .env, or the shipped archive.
    |
    | To install a real production key, set LICENSE_PUBLIC_KEY in .env
    | to the base64 value the owner provides.
    |
    | Empty string means "no key installed yet" — the middleware treats
    | that as an unresolved-license state and behaves per the
    | `fail_closed_when_unconfigured` setting below.
    */
    'public_key_base64' => env('LICENSE_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------
    | Default license duration (days)
    |--------------------------------------------------------------------
    |
    | Per Phase 12.3 §4: default 60 days. Individual license tokens can
    | encode their own `max_days` value; this is only the display default
    | for the initial operating period and the fingerprint output.
    */
    'default_duration_days' => (int) env('LICENSE_DEFAULT_DURATION_DAYS', 60),

    /*
    |--------------------------------------------------------------------
    | Grace period (days)
    |--------------------------------------------------------------------
    |
    | Number of days after `expires_at` during which the middleware still
    | passes requests (with an amber warning banner shown to admins).
    | Set to 0 for hard-expiry behavior.
    */
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 0),

    /*
    |--------------------------------------------------------------------
    | Public storefront behavior on expiry
    |--------------------------------------------------------------------
    |
    | Per §9 default recommendation: public pages (/, /products, /vendors/*)
    | REMAIN VISIBLE after expiry — only login/account/vendor/admin/checkout
    | get blocked. Set true to block the storefront too.
    */
    'block_public_storefront' => (bool) env('LICENSE_BLOCK_PUBLIC_STOREFRONT', false),

    /*
    |--------------------------------------------------------------------
    | Domain binding
    |--------------------------------------------------------------------
    |
    | When enabled, the token's `domain` field must match the request's
    | host (excluding port). This prevents a token issued for one
    | deployment from being reused on another.
    |
    | v12.3.1: the "current host" now comes from `request()->getHost()`
    | in web context (respecting TrustProxies), not from APP_URL. In CLI
    | context (artisan commands, queue workers), we use `LICENSE_DOMAIN`
    | below, falling back to APP_URL as a last resort.
    */
    'require_domain_match' => (bool) env('LICENSE_REQUIRE_DOMAIN_MATCH', true),

    /*
    | The domain used for license binding in CLI/queue contexts where no
    | HTTP request is bound. Leave empty to fall through to APP_URL.
    | Set this if APP_URL differs from the licensed domain (unusual but
    | possible in multi-domain deployments).
    */
    'domain' => env('LICENSE_DOMAIN', ''),

    /*
    | When true, `www.example.com` and `example.com` are treated as
    | equivalent for domain-binding purposes. Default true — most
    | deployments treat the two hostnames as the same site.
    */
    'allow_www_alias' => (bool) env('LICENSE_ALLOW_WWW_ALIAS', true),

    /*
    |--------------------------------------------------------------------
    | Server fingerprint binding
    |--------------------------------------------------------------------
    |
    | When enabled, the token's `server_fingerprint` field must match
    | the fingerprint computed by ServerFingerprintService. Fingerprint
    | changes (rehost, DB rename) require a new license token.
    */
    'require_fingerprint_match' => (bool) env('LICENSE_REQUIRE_FINGERPRINT_MATCH', false),

    /*
    |--------------------------------------------------------------------
    | Warning banner thresholds
    |--------------------------------------------------------------------
    |
    | Days-before-expiry at which the admin dashboard shows an increasing
    | severity banner. Per §11: 14, 7, 3, expired.
    */
    'warning_thresholds' => [14, 7, 3],

    /*
    |--------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------
    |
    | Verified license state is cached for this many seconds to keep the
    | per-request middleware cheap. Cache is invalidated on activation.
    */
    'cache_ttl_seconds' => (int) env('LICENSE_CACHE_TTL_SECONDS', 300),

    /*
    |--------------------------------------------------------------------
    | Fail-closed when unconfigured
    |--------------------------------------------------------------------
    |
    | When enforcement is enabled but no public key is installed OR no
    | activation record exists, this setting decides behavior:
    |   - true  → block protected routes (safest for production)
    |   - false → allow through (safer for staging or partial deploys)
    */
    'fail_closed_when_unconfigured' => (bool) env('LICENSE_FAIL_CLOSED', true),

    /*
    |--------------------------------------------------------------------
    | Installation ID storage
    |--------------------------------------------------------------------
    |
    | The installation UUID is generated ONCE on first fingerprint call
    | and stored at this path. Do NOT delete this file — the fingerprint
    | it produces is baked into every license token issued for this
    | deployment.
    */
    'installation_id_path' => env('LICENSE_INSTALLATION_ID_PATH', 'license/installation_id'),

    /*
    |--------------------------------------------------------------------
    | Exempt route names / URI prefixes
    |--------------------------------------------------------------------
    |
    | Middleware bypass list. These routes are ALWAYS allowed regardless
    | of license state, so the owner can always reach the activation
    | page. Do not add sensitive routes to this list.
    */
    'exempt_route_names' => [
        'login', 'logout',
        'password.request', 'password.email', 'password.reset', 'password.update',
        'verification.notice', 'verification.verify', 'verification.send',
        'license.status', 'license.activate', 'admin.license.index', 'admin.license.activate',
        'locale.update',
    ],

    'exempt_uri_prefixes' => [
        '/health', '/up',
        '/build', '/storage',
        '/license',
        '/admin/license',
    ],

];
