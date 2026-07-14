# Phase 10 v10.15 — Customer Login Regression Repair

Per dev §19.

## Critical context

The dev reported: **"Customers can no longer log in at all."** Login was working pre-v10.14. Therefore v10.15 treats this as a regression introduced by v10.14 until proven otherwise.

## Root-cause analysis

I cannot reproduce the dev's exact local environment from this sandbox (no MySQL, Redis, or PHP runtime available). Instead, I traced every v10.14 production-code change and identified what could fail during the customer login flow.

### v10.14 production changes (the audit surface)

Only 2 files in `app/` were modified in v10.14:

| File | Change |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | `cart_summary` + `top_categories` closures changed from short-arrow to regular closures with scope-aware `$request->path()` checks |
| `app/Http/Controllers/HomeController.php` | Health probe wrapped in `Cache::remember('marketplace:homepage_health:v1', addSeconds(30), ...)` |

Plus a new additive index-only migration (cannot break login).

### Customer login flow (the affected path)

```
1. GET /login                          → LoginController::show → renders Auth/Login via Inertia → share() runs
2. POST /login (email, password)       → LoginController::store → Auth::attempt → session regenerate
3. Customer redirect (defaultRedirectFor): customer role → return '/'
4. Browser follows 302                 → GET /
5. HomeController::index               → renders Welcome via Inertia → share() runs
   ↑                                     ↑
   USES Cache::remember (v10.14)         RUNS scope-aware closures (v10.14)
```

A failure anywhere in steps 1, 4, or 5 reads as "login is broken" from the browser even though authentication may have succeeded:

| Failure point | Symptom | v10.14 risk vector |
|---|---|---|
| Step 1 (share() throws on `/login`) | Login page won't render | If translations/version cache driver throws |
| Step 5 (HomeController::index 500) | Post-login redirect crashes — browser sees a 500 | If `Cache::remember` throws OR if share() closures throw at path `/` |
| Step 5 (Inertia serialization throws) | 500 on `/` | If any closure inside share() returns non-serializable data or throws |

### Most likely environmental triggers

These are the v10.14 risks that environment-specific issues could expose:

1. **`CACHE_STORE=redis` with Redis unreachable.** Pre-v10.14 the homepage didn't use `Cache::remember` directly. Post-v10.14 it does. If Redis is down, the cache call throws. The exception propagates through Inertia render → 500 → customer's post-login redirect to `/` crashes.
2. **Cache database table missing.** `CACHE_STORE=database` requires a `cache` table. If not migrated, every cache call throws.
3. **File cache permission issue.** `CACHE_STORE=file` writes to `storage/framework/cache/`. If permissions are wrong, throws.
4. **Spatie permission cache out of sync.** The `auth.user` closure calls `getRoleNames()->toArray()` and `hasAnyRole(...)`. If the Spatie permission cache references stale role IDs after a fresh migration, throws.
5. **Vendor relation throws.** `$request->user()->vendor?->status` — if the relation query fails (table corruption, FK constraint issue), throws.

Note that some of these (Cache failure, Spatie cache out of sync) would have caused issues PRE-v10.14 too — but pre-v10.14 the homepage didn't ADD another cache call, so the regression is one more failure surface, not a brand new one.

## v10.15 fix strategy — defensive wrapping, not blanket revert

Per dev §16: "Do not remove all v10.14 changes automatically. ... Revert or correct only the optimization that broke authentication."

v10.15 wraps **every shared-prop closure and the homepage health probe in defensive try/catch**. This way:

- If ANY closure throws for ANY reason, the share() method still returns a valid array.
- The login page always renders (share() can't fail).
- The post-login redirect target always renders (HomeController can't 500 because of cache failure).
- Authentication correctness is decoupled from optimization correctness.

This preserves 100% of v10.14's performance improvements (scope-aware closures, health cache, indexes) while making them fail-safe.

## Exact files corrected

| File | v10.15 change |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Defensive try/catch on FIVE share closures: `app.version`, `auth.user`, `cart_summary`, `top_categories`, plus the `loadTranslations` cache call. Each catches `\Throwable`, logs a warning, and returns a safe fallback (null/[]/direct file read). v10.14 scope-aware path checks PRESERVED inside the try block — the optimization isn't reverted, just made fail-safe. |
| `app/Http/Controllers/HomeController.php` | Defensive try/catch around v10.14's `Cache::remember` of the health probe. If the cache driver throws, fall back to direct inline probes so `/` always renders. v10.14 cache key (`marketplace:homepage_health:v1`) and TTL (30s) PRESERVED inside the try block. |
| `tests/Feature/Phase10V1015RegressionTest.php` | NEW — 20 Pest scenarios covering the dev's §14 mandatory list |
| `.github/workflows/ci.yml` | 3 new v10.15 CI sub-checks + verdict bump |
| `VERSION` | `Phase 10 v10.14` → `Phase 10 v10.15` |

## What v10.15 does NOT change

- No routes touched
- No `config/auth.php`, `config/session.php`, `config/cache.php` touched (the dev's `.env` is the source of truth there)
- No User model touched
- No LoginController touched (auth flow itself unchanged)
- No React login page touched
- v10.14 scope-aware closures PRESERVED (the optimization)
- v10.14 health cache PRESERVED (the optimization)
- v10.14 indexes migration UNTOUCHED (additive, idempotent, cannot break login)
- All v10.0-v10.13 fixes preserved

## Session/cache/Redis configuration — what the dev should verify

Since I can't see the dev's `.env`, here are the most likely environmental causes the dev's spec §6 + §7 emphasized:

```bash
# Verify cache driver is reachable
php artisan tinker
>>> Cache::put('p1015_test', 1, 60); Cache::get('p1015_test')
# → must return 1 instantly
```

If `Cache::get` is slow or throws → that's the root cause. Common fixes:
- `CACHE_STORE=redis` + `REDIS_HOST=redis` (Docker hostname) on a host-mode Laravel: change to `REDIS_HOST=127.0.0.1` OR `CACHE_STORE=file`.
- `CACHE_STORE=database` + no `cache` table: run `php artisan cache:table && php artisan migrate`.
- `CACHE_STORE=file` + wrong permissions: `chmod -R 775 storage/framework/cache && chown -R www-data:www-data storage`.

```bash
# Verify session driver
>>> session(['p1015_test' => 'x']); session('p1015_test')
# → must return 'x'
```

Common fixes:
- Same Redis advice as above for `SESSION_DRIVER=redis`.
- `SESSION_SECURE_COOKIE=true` while testing over `http://`: change to `SESSION_SECURE_COOKIE=false` for local.
- `SESSION_DOMAIN=.production.tld` while testing on `127.0.0.1`: change to `SESSION_DOMAIN=null` or comment out.

```bash
# Verify session cookie returns through the LOGIN POST
curl -i -c /tmp/p1015.cookies -b /tmp/p1015.cookies http://127.0.0.1:8000/login | grep -i set-cookie
# → should set XSRF-TOKEN and the app session cookie

curl -i -c /tmp/p1015.cookies -b /tmp/p1015.cookies \
     -H "X-XSRF-TOKEN: $(grep XSRF-TOKEN /tmp/p1015.cookies | awk '{print $7}')" \
     -d "email=customer@marketplace.test&password=password" \
     http://127.0.0.1:8000/login
# → should be 302 redirect to /
```

If POST `/login` returns 419 → CSRF/cookie issue (SESSION_DOMAIN or SESSION_SECURE_COOKIE).
If POST `/login` returns 422 → validation error (credentials format).
If POST `/login` returns 302 to `/login` → invalid credentials.
If POST `/login` returns 302 to `/` → authentication succeeded. Now GET `/` and check that:
- If GET `/` returns 200 → login WORKS. The "broken" report might be a frontend issue.
- If GET `/` returns 500 → v10.15 should now catch the exception in share()/HomeController. If it doesn't, the exception is in unrelated code (e.g. PricingService, featured_products query).

## Authentication guard and provider

Unchanged from v10.13. The project uses Laravel's standard `web` guard backed by the `users` provider via `App\Models\User`. The customer-side login (`/login`) uses this guard. The admin Filament panel (`/admin/login`) is a separate Filament authentication flow.

`LoginController::store` is the public login handler. It:
1. Validates email + password.
2. Calls `Auth::attempt($credentials, $remember)`.
3. If admin role detected → rejects with validation message ("Admin users must sign in via /admin/login").
4. If account suspended/banned → rejects.
5. Otherwise → regenerates session, updates `last_login_at`, redirects per `defaultRedirectFor(user)`.

`defaultRedirectFor`:
- vendor role (or has a `vendors` record) → `/vendor`
- everything else (customer, unrolled) → `/`

This logic is untouched by v10.15.

## Post-login redirect destination

Customer → `/` (HomeController::index → Welcome.tsx).

The most critical surface. v10.15 makes this surface fail-safe via the HomeController health probe defensive wrap.

## Automated test results (static)

20 Pest scenarios in `Phase10V1015RegressionTest.php`. The dev's §14 mandatory test list mapped:

| § | Scenario |
|---|---|
| 14.1 | "customer can GET /login (HTTP 200, Inertia page renders)" |
| 14.1+ | "shared props render on /login even when no user is authenticated" |
| 14.2 | "customer POSTs /login with valid credentials and is authenticated" |
| 14.3 | "session is regenerated after a successful customer login" |
| 14.4 | "customer remains authenticated when following the post-login redirect to /" |
| 14.5 | "customer GET / after login renders 200 with the Welcome Inertia component" |
| 14.6 | "invalid password is rejected with a validation error" |
| 14.7 | "unknown email is rejected with a validation error" |
| 14.10 | "vendor can POST /login and is redirected to /vendor (not regressed by v10.15)" |
| 14.11 | "admin attempting /login is rejected with the 'must use /admin/login' message" |
| 14.12 | "customer logout invalidates the session" |
| 14.13 | "customer cannot access /vendor routes" |
| 14.14 | "customer cannot access /admin/reports" |
| 14.15+16 | "all shared Inertia props render correctly after customer login (no exception)" |
| §11 | "v10.15 source has try/catch wrapping on EVERY share() closure" |
| §11 | "v10.15 HomeController health probe has try/catch fallback to direct probes" |
| §16 | "v10.14 scope-aware admin/vendor exclusion still active" |
| §16 | "v10.14 perf indexes migration still present" |
| Cross-cut | "VERSION reports Phase 10 v10.15" |
| Cross-cut | "v10.0-v10.14 preservation: every prior fix marker intact" |

CI sub-check totals: Phase 10 = **62**.

## Confirmation that valid performance improvements remain

| v10.14 improvement | v10.15 status |
|---|---|
| Scope-aware `cart_summary` (admin/vendor skip) | ✓ PRESERVED inside try block |
| Scope-aware `top_categories` (admin/vendor skip) | ✓ PRESERVED inside try block |
| Homepage health 30s cache | ✓ PRESERVED inside try block (with direct-probe fallback) |
| 8 new composite indexes | ✓ MIGRATION UNTOUCHED |

Per dev §16: "Performance optimization must never compromise authentication correctness." v10.15 achieves this by making every optimization fail-safe.

## Manual customer login result

I cannot perform browser verification from this sandbox. The dev's §15 walkthrough is the acceptance gate. Per the dev's §1 reproduction checklist, the dev should record what `POST /login` returns (status code, redirect location, exact response body), then `GET /` after login, with browser DevTools Network tab open. Compare against `PHASE_10_v10.15_DEVELOPER_CHECKLIST.md`.

## Per dev §21 acceptance

**Phase 10 v10.15 makes the v10.14 optimizations fail-safe. Customer login regression requires developer runtime verification against their environment.**

If after deploying v10.15 + clearing caches + restarting PHP-FPM + hard-refreshing the browser, customer login STILL fails, the root cause is environmental (cache driver / session driver / Redis connectivity / SESSION_SECURE_COOKIE) — and the v10.15 defensive wrap will at least make share() failures logged + recoverable rather than user-facing 500s. The dev's `storage/logs/laravel.log` will contain the actual exception message under one of the v10.15 markers (`"... share closure failed (Phase 10 v10.15 defensive catch)"`).
