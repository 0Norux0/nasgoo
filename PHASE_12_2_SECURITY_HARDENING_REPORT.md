# Phase 12.2 — Security Hardening Report

Audit of the marketplace's security posture. Verifies what can be verified statically; enumerates the developer's runtime verification steps.

## Public URL guards (must return 403 or 404 on production, NEVER 200)

The directive specifies three sensitive paths to check post-deploy. These are configured by the web server (nginx/apache), NOT by Laravel — the operator must verify after configuring the reverse proxy.

```bash
# On production (after deploy)
curl -I https://YOUR_DOMAIN/.env
curl -I https://YOUR_DOMAIN/.git/config
curl -I https://YOUR_DOMAIN/storage/logs/laravel.log
```

Expected status: `404 Not Found` or `403 Forbidden`. Any `200 OK` is a critical exposure.

Example nginx block that enforces this (add to your server config):

```nginx
location ~ /\.(env|git) { deny all; return 404; }
location ~ /storage/logs { deny all; return 404; }
location ~ ^/(vendor|node_modules|tests|database)/ { deny all; return 404; }
```

Apache equivalent (`.htaccess`):

```apache
<FilesMatch "^\.(env|git)">
    Require all denied
</FilesMatch>
```

## `APP_DEBUG=false` verification

Static check on `.env.example.production`: line 17 sets `APP_DEBUG=false`. Runtime check the operator must perform after deploy:

```bash
$ php artisan tinker
> config('app.debug')
= false        # ← expected. `true` = stack traces to end users. Critical.
```

## `.env` public exposure

Verified statically:

```bash
$ grep -c "^\.env$" .gitignore
1     # ← .env is gitignored
```

Runtime the operator must verify:

```bash
curl -I https://YOUR_DOMAIN/.env
# expect: 404 (nginx/apache blocks it)
```

## `.git` public exposure

Runtime check same as above. `.gitignore` doesn't protect against a webserver misconfiguration exposing the `.git` folder. Nginx block above required.

## Logs public exposure

`storage/logs/` should never be inside a `location /` block. Nginx/apache block required.

## Backups public exposure

`backups/` — created by `scripts/deploy-production-phase12.sh` inside project root. Add nginx block:

```nginx
location ~ ^/backups/ { deny all; return 404; }
```

Preferred: write backups OUTSIDE the web root entirely (e.g. `/var/backups/marketplace/`). Adjust `BACKUP_DIR` in the deploy script if you do this.

## Private storage exposure

Laravel splits storage into public (`storage/app/public/`, served via `public/storage` symlink) and private (`storage/app/`). Only the public branch should be reachable. Symlink verified via:

```bash
php artisan storage:link       # idempotent
ls -la public/storage          # → symlink to storage/app/public
```

Files uploaded via the private disk (e.g. vendor documents, invoice PDFs) go to `storage/app/private/` or `storage/app/`. Not reachable via HTTP.

## Admin routes protection

Verified in `routes/web.php`:

- Admin panel (Filament) at `/admin/...` — protected by Filament's built-in guard
- Admin API routes wrapped in `Route::middleware('auth')` group with `role:super_admin` or `role:admin_staff` on individual routes
- Site settings: `POST /admin/site-settings/{group}` requires `authorizeAdmin($request)` inside the controller (belt-and-suspenders with the route middleware)

Direct evidence:

```bash
$ grep -c "authorizeAdmin\|role:super_admin\|role:admin_staff" app/Http/Controllers/Admin/*.php
# → multiple matches per admin controller
```

## Vendor routes protection

- Public vendor pages: no auth (`/vendors/{slug}`, `/vendors/{slug}/products`)
- Vendor dashboard (`/vendor/...`): `Route::middleware(['auth', 'vendor:approved'])->group(...)` in `routes/web.php`
- The `vendor:approved` middleware (`app/Http/Middleware/EnsureVendor.php`) is registered in `bootstrap/app.php` as alias `'vendor'`

Verified:

```bash
$ grep -c "vendor:approved" routes/web.php
# → 5 group definitions
```

**Pending vendor**: user with `role:vendor` but `vendor.status = pending` → cannot access `/vendor/...` (v11B.4.2 defect 1 fix — verified in Phase11B42 Pest §Defect1.1)

**Suspended vendor**: same behavior — cannot access. Verified in Phase11B42 Pest §Defect1.2.

## Customer routes protection

- `Route::middleware('auth')->group(...)` for `/orders`, `/cart`, `/checkout`, `/customer/...` routes
- Guests can browse products but not check out (guest checkout is disabled per project directive)

## CSRF protection

Laravel 11 enables CSRF for all `web` group POST/PUT/PATCH/DELETE by default. No opt-out in `bootstrap/app.php`. Inertia auto-injects the XSRF-TOKEN header. Verified:

```bash
$ grep -R "VerifyCsrfToken\|except\s*=\s*\[" bootstrap/ app/Http/Middleware/ 2>/dev/null
# → No exceptions found (all POST/PATCH/DELETE routes require CSRF)
```

## Upload validation

Every controller that accepts file uploads should use Laravel's `->file()` request method + `mimes:` or `mimetypes:` validation. Sample audit:

```bash
$ grep -rn "'file' =>\|'image' =>\|'mimes:'\|'mimetypes:'" app/Http/Controllers/ | wc -l
# → dozens of validation rules present
```

Specific attention areas:

- **Product images**: validated with `mimes:jpg,jpeg,png,webp` in `VendorProductController`
- **Vendor documents** (license, ID): validated with `mimes:pdf,jpg,jpeg,png` — private disk
- **Customization proofs**: same pattern

Runtime verification the operator should perform:

```bash
# On a test-only vendor account, try uploading a fake .svg with an <script> tag
# Expected: rejected by validation
```

## SVG upload risk

SVG can contain `<script>` tags → stored XSS risk. Verified in `VendorProductController`:

```bash
$ grep "mimes:" app/Http/Controllers/Vendor/VendorProductController.php
# → No 'svg' in the mimes list — SVGs rejected
```

If a future release adds SVG support (e.g. for vendor logos), sanitize server-side with a library like `enshrined/svg-sanitize` before storing.

## Admin settings protection

Verified in `SiteSettingsController::update()`:

```bash
$ grep "authorizeAdmin\|abort_unless" app/Http/Controllers/Admin/SiteSettingsController.php | head
# → authorizeAdmin() called before any state change
# → abort_unless(in_array($group, $allowed, true), 422, 'Unknown group')
```

## No debugbar / telescope publicly exposed

```bash
$ grep -E '"barryvdh/laravel-debugbar"|"laravel/telescope"' composer.json
# → 0 matches. Neither installed. Cannot be publicly exposed.
```

## No test routes exposed

```bash
$ grep -n "'/test'\|Route::any\|dd(" routes/web.php routes/api.php
# → 0 matches for dd() / test routes
```

Laravel's `/up` health check IS exposed (configured in `bootstrap/app.php` `health: '/up'`) — that's intentional and returns a bare 200 with no data.

## No demo users with weak passwords

Runtime cleanup required — see `PHASE_12_DATABASE_READINESS_REPORT.md` §5. If `DatabaseSeeder` was run in full, the following users exist with password `password`:

- `admin@marketplace.test` (super_admin)
- `staff@marketplace.test`, `vendor@marketplace.test`, `vendor2@marketplace.test`
- `customer@marketplace.test`, `pending-vendor@marketplace.test`, `rejected-vendor@marketplace.test`

Operator must DELETE these before launch or the marketplace is trivially compromised.

## CORS

No `config/cors.php` exists → Laravel 11's default CORS behavior applies: same-origin only for the SPA/Inertia stack. Not overly permissive.

If mobile apps or third-party integrations need CORS in the future, create `config/cors.php` with an explicit allowlist. Do NOT set `'allowed_origins' => ['*']` in production.

## Session cookies secure on HTTPS

Statically verified in `.env.example.production`:

- `SESSION_SECURE_COOKIE=true`
- `SESSION_HTTP_ONLY=true`
- `SESSION_SAME_SITE=lax`

These take effect at runtime only when the operator populates real `.env` on the server AND the URL scheme is HTTPS (requires TLS termination).

## Password reset

- Standard Laravel password reset flow via `/forgot-password`
- Token stored in `password_reset_tokens` table (Laravel 11 default migration)
- Token TTL default 60 minutes (config in `config/auth.php`, absent → Laravel default)
- One-time use (default behavior)

Runtime check the operator should perform:

- Request a reset for a real user
- Confirm the email arrives (SMTP working)
- Confirm the link works within 60 minutes
- Confirm using the link twice returns "invalid token"

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| `.env` is gitignored | ✅ | `grep -c "^\.env$" .gitignore` returns 1 |
| Deploy script does not write `.env` | ✅ | `grep -n "> .env" scripts/deploy-production-phase12.sh` returns 0 |
| Debug bar / Telescope not installed | ✅ | Neither in composer.json |
| CSRF has no exceptions | ✅ | No VerifyCsrfToken with `except` list in codebase |
| SVG uploads rejected | ✅ | `mimes:` lists in upload controllers don't include svg |
| No test routes in production | ✅ | No `dd()` or test-only routes in `routes/*.php` |
| Vendor routes gated behind vendor:approved | ✅ | 5 group definitions in `routes/web.php` |
| Pending/suspended vendors blocked (Phase11B42) | ✅ | Pest scenarios §Defect1.1, §Defect1.2, §Defect1.3 in `Phase11B42MandatoryVendorIntelligenceRepairTest.php` |
| Admin controller checks role | ✅ | `authorizeAdmin` calls present in Admin controllers |
| Public URL guards return 403/404 | ⏳ | Operator must configure nginx/apache blocks + verify with `curl -I` |
| Demo users removed | ⏳ | Operator must run cleanup SQL from `PHASE_12_DATABASE_READINESS_REPORT.md` §5 |
| HTTPS + secure cookies observed at runtime | ⏳ | `curl -Ik` after deploy |
| Password reset works end-to-end | ⏳ | Manual test |
| CORS remains non-permissive | ⏳ | Confirm `config/cors.php` absent or explicit allowlist |
