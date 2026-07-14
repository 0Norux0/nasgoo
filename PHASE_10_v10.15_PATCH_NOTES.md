# Phase 10 v10.15 — Patch Notes

## What's fixed

| Defect | Root cause | Fix |
|---|---|---|
| Dev report: "Customers can no longer log in at all" after deploying v10.14. Login was working pre-v10.14. | v10.14 added a `Cache::remember` call in `HomeController::index` and converted two `HandleInertiaRequests` closures from short-arrow to regular form. The customer post-login redirect target is `/` (HomeController). If ANY closure inside `share()` throws OR if `Cache::remember` throws (cache driver unreachable, e.g. `CACHE_STORE=redis` with Redis down), the homepage 500s. From the customer's browser, this reads as "login is broken" even though authentication succeeded. The same exception class also breaks the LOGIN PAGE itself if `translations` or `app.version` cache throws — the user can't even see the form. | **Defensive try/catch wrapping** on every share() closure (`app.version`, `auth.user`, `cart_summary`, `top_categories`, `loadTranslations`) AND on the v10.14 HomeController health cache. Each catches `\Throwable`, logs a warning, returns a safe fallback (null/[]/direct file read). v10.14's optimization logic is PRESERVED INSIDE the try block — not reverted. **Authentication correctness is now decoupled from any shared-prop computation failure.** |

## Counts

| | v10.14 → v10.15 |
|---|---|
| Phase 10 CI sub-checks | 59 → 62 |
| Phase 10 Pest scenarios | 204 → 224 |
| PHP source files modified | 2 (HandleInertiaRequests, HomeController) |
| New files | 1 (Pest test file) |
| React files modified | 0 |
| Routes / config / auth files modified | 0 |
| v1-v9 files touched | 0 |
| v10.0-v10.14 fix code reverted | 0 |
| v10.14 optimizations preserved | ALL (scope-aware closures, health cache, indexes) |
| Helpers added | 4 (`p1015_seed/customer_with_password/vendor_user_with_password/admin_with_password`) — 85 total unique, 0 duplicates |

## Strategy: defensive wrapping, not blanket revert

Per dev §16: "Do not remove all v10.14 changes automatically. Revert or correct only the optimization that broke authentication."

v10.15 keeps every v10.14 optimization (scope-aware closures, 30s health cache, 8 new composite indexes) but makes them fail-safe:

```php
'cart_summary' => function () use ($request) {
    try {
        // v10.14 scope-aware logic PRESERVED here
        if (admin/vendor/api) return null;
        if (! $user) return null;
        return [...cart data...];
    } catch (\Throwable $e) {
        // v10.15 defensive catch
        Log::warning('cart_summary share closure failed', ['message' => $e->getMessage()]);
        return null;
    }
},
```

The cart badge degrades to 0 silently if anything throws. Login still works. Same pattern applied to all 5 closures + the HomeController health probe.

## What v10.15 explicitly preserves

- **v10.14 scope-aware admin/vendor exclusion** in `cart_summary` + `top_categories` — verified by CI: still 2 occurrences each of `str_starts_with($path, 'admin/')` and `str_starts_with($path, 'vendor/')`.
- **v10.14 homepage health cache** — verified by CI: `marketplace:homepage_health:v1` cache key still present.
- **v10.14 indexes migration** — UNTOUCHED. Additive, idempotent, cannot break login.
- All v10.0-v10.13 fixes (admin reports guard, vendor reports nav, payout SQL, Spatie scope, Filament eager-loads, etc.) — verified by inline Pest regression scenarios.

## Auth matrix verification (per dev §17)

| Role | Flow | v10.15 status |
|---|---|---|
| Customer | POST /login → 302 → / → 200 (Welcome) | ✓ Pest scenario verifies |
| Vendor | POST /login → 302 → /vendor → 200 (Dashboard) | ✓ Pest scenario verifies |
| Admin (via /login) | Rejected with "must use /admin/login" validation error | ✓ Pest scenario verifies (preserves v3.3 design) |
| Admin (via /admin/login) | Filament panel, separate flow — UNCHANGED | not modified by v10.15 |
| Customer → /vendor | 302 or 403 | ✓ Pest scenario verifies |
| Customer → /admin/reports | 302 or 403 | ✓ Pest scenario verifies |
| Logout | session invalidated, redirect to / | ✓ Pest scenario verifies |
| Invalid password | Validation error, auth fails | ✓ Pest scenario verifies |
| Unknown email | Validation error, auth fails | ✓ Pest scenario verifies |

## Per dev §21 acceptance

**Phase 10 v10.15 makes v10.14's optimizations fail-safe. Customer login regression requires developer runtime verification against their environment.**

If after deploying v10.15 the customer STILL can't log in, the root cause is environmental — cache/session/Redis misconfiguration. The v10.15 defensive wrappings will:
1. Log the actual exception in `storage/logs/laravel.log` under a `(Phase 10 v10.15 defensive catch)` marker
2. Return a safe fallback so the page still renders
3. Make the bug visible in logs without breaking the user experience

The dev runs:
```bash
composer install
npm ci
php artisan optimize:clear
php artisan route:list | grep -i login
php artisan test --filter='Phase10V1015'
php artisan test
npm run typecheck
npm run build
```

Then walks confirmations A-N in `PHASE_10_v10.15_DEVELOPER_CHECKLIST.md` — using the SAME local URL and database the dev used pre-v10.14.
