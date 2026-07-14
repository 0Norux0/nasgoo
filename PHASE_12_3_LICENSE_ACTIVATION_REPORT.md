# Phase 12.3 — License Activation Report

Ownership-protection layer for the marketplace. This phase adds ONLY the license gate — no changes to cart, checkout, products, vendors, orders, recommendations, personalization, vendor intelligence, pricing, or Phase 12.2 production-readiness logic.

## Why not a hardcoded password

The obvious "protection" is embedding a secret in the source. That fails immediately: any developer with source-code access (including whoever hosts the marketplace) can grep for it, delete the check, or share the secret. It's security theatre.

The solution here uses asymmetric cryptography — specifically Ed25519 signatures. The signing key stays with the owner (Aamir). The app only holds the paired public verification key. The app cannot forge a valid license token; only the holder of the private key can. Removing the verification check from source code is possible for a determined attacker — but that changes the threat model from "trivially bypassed" to "requires code modification and re-deployment", which is a much higher bar and creates an audit trail.

The directive §17 explicitly asks for honesty about this: **code-level protection is not absolute**. It raises the bar; it doesn't eliminate the risk. That's the honest framing.

## Architecture

**Token format** (JWT-adjacent, with a distinct type discriminator):

```
base64url(header) . base64url(payload) . base64url(signature)
```

- `header`: `{"alg":"EdDSA","typ":"MPLIC"}`
- `payload`: the JSON per §5 — license_holder, domain, expires_at, issued_at, max_days, license_type, server_fingerprint, nonce
- `signature`: raw 64-byte Ed25519 signature over `header.payload`

**Why Ed25519** over RSA/ECDSA:

- 32-byte public key, 64-byte signature — small enough to embed in `.env`
- Deterministic (no per-signature nonce risk)
- Standard in PHP via `ext-sodium` (built into PHP 7.2+, no third-party library needed)
- Constant-time verification via `sodium_crypto_sign_verify_detached`
- Broadly reviewed cryptography

**Verification chain:**

1. Middleware `EnsureValidLicense` → `LicenseManager::shouldBlockRequest($request)`
2. `LicenseManager` reads cached state (5-min TTL default) OR calls `computeStatus()`
3. `computeStatus()` reads the most recent `active` row from `license_activations`
4. For fresh activation: `LicenseManager::activate($token)` → `LicenseVerifier::verify($token, $expectedDomain, $expectedFingerprint, $graceDays)`
5. `LicenseVerifier` decodes the token, verifies signature, checks expiry + domain + fingerprint claims
6. Every attempt (success + failure) is written to `license_audit_logs`

## Files delivered

### Configuration

- `config/license.php` — every knob documented inline
- `.env.example.production` — new `LICENSE_*` section with safe defaults

### Migration (additive, no existing tables changed)

- `database/migrations/2027_02_01_000001_create_license_tables.php`
  - `license_activations` (14 columns + 2 indexes)
  - `license_audit_logs` (immutable, 8 columns + 2 indexes)

### Services

- `app/Services/Licensing/LicenseVerifier.php` — pure signature verification, no side effects
- `app/Services/Licensing/LicenseManager.php` — orchestrator (cache, DB, audit, gate)
- `app/Services/Licensing/ServerFingerprintService.php` — installation UUID + host + DB hash

### Middleware

- `app/Http/Middleware/EnsureValidLicense.php` — registered in `bootstrap/app.php` as alias `license` + appended to `web` group

### Controller + routes

- `app/Http/Controllers/Admin/LicenseController.php` — 3 actions (index, activate, publicStatus)
- `routes/web.php` — added `/license/status` (public) + `/admin/license` (auth) + `/admin/license/activate` (auth, throttled)

### React pages

- `resources/js/Pages/Admin/License/Index.tsx` — activation UI with status cards, banner, copy-fingerprint buttons
- `resources/js/Pages/License/Status.tsx` — minimal public page
- `resources/js/Pages/License/Blocked.tsx` — 403 page for authed non-admin users

### Artisan commands

- `license:status` (`--json`)
- `license:fingerprint` (`--json`)
- `license:activate {token} [--no-cache-clear]`
- `license:clear-cache`

### Offline generator tool (NOT part of runtime)

- `tools/license-generator/generate.php` — standalone CLI, requires `ext-sodium`
- `tools/license-generator/README.md` — owner instructions

### Tests

- `tests/Feature/Phase12_3LicenseActivationTest.php` — 20 scenarios covering every §11 test case

## Preservation

Every file from v12.2.2 remains byte-identical except:

- `bootstrap/app.php` — added `license` alias + appendToGroup on web
- `routes/web.php` — appended 3 routes at end
- `.env.example.production` — inserted `LICENSE_*` section before pre-deployment checklist
- `VERSION` — bumped

No application code (controllers, services, models) outside the new `Licensing/` namespace was touched. No migration was modified — only one new additive migration was added. Vendor intelligence, personalization, pricing, checkout, orders, admin site-settings — all unchanged.

## Behavior summary

- **enforcement_enabled=false** (default): middleware is a no-op passthrough. Existing installs boot without any license check.
- **enforcement_enabled=true + no key installed**: `fail_closed_when_unconfigured` decides. Default `true` = block; `false` = allow.
- **Active license**: passthrough for all routes.
- **Grace period**: passthrough with amber admin banner.
- **Expired**: super_admin → redirect to `/admin/license`; other authed → 403; guest → storefront (unless `block_public_storefront=true`).
- **Public storefront** stays visible by default even when expired (§9 default recommendation).
- **NO data is ever deleted or altered** by any license-related code path.

## Sandbox declaration (honest reporting)

I built and tested Phase 12.3 without a PHP runtime, without a MySQL database, and without npm registry access. Here's exactly what I did and did NOT verify:

### ✅ Verified in sandbox

- Ed25519 signing + verification round-trip using Python `cryptography` library — produces identical byte-for-byte output as PHP's `sodium_crypto_sign_detached` (same RFC 8032 standard). Sample token verified against generated public key.
- TypeScript type-checking on the new React pages — no new error classes introduced beyond the pre-existing sandbox-typical `TS7006 implicit any` (from missing `@types/react`), which is the same as baseline.
- Structural PHP sanity via custom Python analysis — balanced braces/parens, correct `<?php` prefix, correct namespaces, no `match => &$var` patterns (the parse-error bug from v12.2.1).
- File presence: 20 new files, all under expected paths.

### ⏳ NOT verified — the developer must run in production

- `php artisan test --filter=Phase12_3License` — 20 scenarios written but pass/fail unverified. Every scenario is grounded in real API calls to `LicenseVerifier`, `LicenseManager`, and HTTP routes; no scenario is speculative.
- `php artisan test` — full 1,556-scenario baseline + 20 new = 1,576 total, unverified.
- `php artisan migrate` — the new migration is trivial (`Schema::create` on two tables), unverified.
- `npm run typecheck` — sandbox has no `node_modules`, so cannot run the real project typecheck. My tsc run showed only sandbox-typical errors.
- `npm run build` — Vite build not runnable in sandbox.
- `npm run lint` / `npm run format:check` — not runnable without npm install.
- `grep -R "PRIVATE KEY" .` — I ran this against the workspace; 0 hits. See `PHASE_12_3_PACKAGE_INTEGRITY.md` for the full audit.

### ⏳ Recommended smoke test on the developer's side

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate --force
php artisan license:status
php artisan license:fingerprint
php artisan route:list | grep -i license
php artisan test --filter=Phase12_3License
php artisan test
```

Then generate a keypair with the offline tool and try activating a token via `/admin/license`. See `PHASE_12_3_LICENSE_DEVELOPER_CHECKLIST.md` for the full verification sequence.

## Limitations of app-side licensing

Per §17, this is explicit:

- A developer with source-code access CAN comment out the middleware. Nothing in the app prevents that. The audit trail (Git history + `license_audit_logs`) makes it detectable, but not preventable.
- The private signing key MUST stay with the owner. If it's ever included in the archive (by accident) or leaked, the entire protection collapses. The generator's `sodium_memzero` calls minimize in-memory exposure but can't help if the key was already on disk somewhere it shouldn't be.
- Rehosting to a new server / domain / DB requires a new token. That's a feature — it prevents casual copying — but also a cost. The `license:fingerprint` command is the escape hatch: it prints the identifiers the owner needs to sign a fresh token.
- The public-storefront-visible default means an expired license still shows product pages. That's a policy choice (per §9 default recommendation) — customers can browse, but they cannot check out. Set `LICENSE_BLOCK_PUBLIC_STOREFRONT=true` for a harder posture.

**Phase 12.3 STOPS HERE.** Awaiting the developer's verification run against a real PHP + MySQL environment.
