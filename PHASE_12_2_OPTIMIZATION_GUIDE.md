# Phase 12.2 — Optimization Guide

Production optimization commands + safety guidance. All commands are non-destructive but their order matters.

## Full production optimization sequence

Run in this order after every deploy:

```bash
# Dependencies (must be current)
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Clear ALL caches before rebuilding
php artisan optimize:clear

# Rebuild caches in the correct order
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Order matters because `route:cache` reads the config it caches; if you `route:cache` before `config:cache`, the routes get baked with the pre-cache config values (usually the same, but a footgun if config changes).

`scripts/deploy-production-phase12.sh` runs this sequence in step 9 with `optimize:clear` first.

## What each cache does

| Command | What it does | Safe to run always? |
| --- | --- | --- |
| `optimize:clear` | Runs `config:clear`, `route:clear`, `view:clear`, `event:clear`, `cache:clear` | Yes |
| `config:cache` | Serializes all `config/*.php` files to `bootstrap/cache/config.php`. `env()` STOPS working outside `config()` | Yes IF you follow the rule below |
| `route:cache` | Serializes all `Route::` definitions to `bootstrap/cache/routes-v7.php` | Yes IF no closures in routes |
| `view:cache` | Precompiles Blade views to `storage/framework/views/` | Yes, always |
| `event:cache` | Serializes event → listener mappings | Yes, always |
| `cache:clear` | Empties app cache store (Redis / DB) | Yes, but flushes rate limits, sessions if session driver is cache-backed |

## The `env()` rule

After `config:cache`, `env()` returns `null` for anything called OUTSIDE `config/*.php`. Every use of `env()` in `app/`, `routes/`, or `resources/` breaks silently.

Audit:

```bash
$ grep -rn "env('" app/ routes/ resources/ 2>/dev/null
```

Expected: 0 matches (all env access should go through `config('site.foo')` after being wrapped in `config/site.php`).

If matches are found, either:
- Move the `env()` call into a config file that returns from there
- Add `config()` accessor and use that everywhere

## Route closures

`route:cache` fails on closure-based routes:

```php
// This BREAKS route:cache:
Route::get('/foo', function () { return 'ok'; });

// This works:
Route::get('/foo', [FooController::class, 'index']);
```

Audit:

```bash
$ grep -n "function\s*()\s*{" routes/web.php routes/api.php
```

Any matches must be refactored to controller methods before `route:cache` will work.

## Verifying caches work

After running the sequence, check the app responds correctly:

```bash
# Homepage
curl -sI https://YOUR_DOMAIN/ | head -1
# Expected: HTTP/2 200

# A vendor page
curl -sI https://YOUR_DOMAIN/vendors/some-vendor | head -1
# Expected: HTTP/2 200 (or 302 to login if unauthorized)

# API endpoint
curl -sI https://YOUR_DOMAIN/api/health 2>/dev/null || curl -sI https://YOUR_DOMAIN/up
# Expected: HTTP/2 200
```

If a page 500s only in production but works in local, the cause is usually `env()` in production code (see the rule above).

## Settings invalidation after cache

The marketplace uses a runtime settings store (`SiteSettingsService` → `settings` table). Admin changes to site settings should NOT require `php artisan config:cache` to see changes.

Confirmed in `app/Services/Settings/SiteSettingsService.php`:

```bash
$ grep -n "Cache::forget\|invalidate" app/Services/Settings/SiteSettingsService.php
# → invalidate() called from setMany() (line ~in the update path)
```

Runtime settings live in Laravel's cache store (not the config cache). `SESSION_STORE=redis` + `CACHE_STORE=redis` means these invalidations propagate across all app workers instantly.

## Arabic / RTL and cache

Blade view caching preserves the compiled RTL logic. Runtime language switching still works after `view:cache`. Verified path:

```bash
$ grep -n "SetLocale\|__(" app/Http/Middleware/SetLocale.php
# → SetLocale middleware sets `app()->setLocale($locale)` per request
# → __() calls at Blade compile time are DEFERRED to render time (Laravel's default)
```

If `view:cache` is producing English-only Blade output, the cause is usually a rare pattern like `@include('components.x-'.$locale.'.foo')` — where the locale is baked into the template PATH at compile time. Grep for it if you see it.

## Rollback of cached state

If a bad deploy left `config:cache` in a broken state:

```bash
php artisan optimize:clear      # clears ALL caches
# Re-verify /up returns 200
# Then rebuild caches from scratch
```

## Composer optimizations

- `--no-dev` — excludes phpunit, mockery, etc. Saves ~100 MB and prevents dev dependencies from being autoloaded.
- `--optimize-autoloader` — flattens the PSR-4 map. ~5-10% faster autoload on the hot path.
- `--classmap-authoritative` — same as `--optimize-autoloader` but ALSO skips filesystem checks. Fastest, but breaks if you `require` a file not in the classmap. Optional.

## Frontend build optimizations

- `npm ci` (not `npm install`) — uses `package-lock.json` for reproducible builds. Faster.
- `npm run build` — Vite produces minified, tree-shaken output. Uses production-mode Rollup config.
- `NODE_ENV=production npm run build` — belt-and-suspenders; Vite already infers this from the build command, but the env var doesn't hurt.

Vite output goes to `public/build/`. Contains a `manifest.json` — Laravel's `@vite` directive reads this to inject the right hashed filenames into HTML.

## Common failure modes and fixes

| Symptom | Cause | Fix |
| --- | --- | --- |
| 500 on all pages after deploy | `config:cache` cached `null` from `env()` in app code | Fix `env()` calls, `optimize:clear`, re-cache |
| CSS/JS not loading, "Vite manifest not found" | `npm run build` didn't run OR wrong `public/build/manifest.json` | Re-run `npm ci && npm run build` |
| Admin sees stale settings | Settings service cache not invalidating | Check `SiteSettingsService::setMany` calls `invalidate()` |
| RTL text renders LTR | Blade compiled with wrong locale | `view:cache` clear + `SetLocale` middleware check |
| Route returns 404 that worked before | `route:cache` didn't include new route | `route:clear` then `route:cache` |
| Queue jobs use stale code | Worker not restarted after deploy | `php artisan queue:restart` |

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| Deploy script runs optimize sequence | ✅ | `scripts/deploy-production-phase12.sh` step 9 |
| No `env()` calls outside `config/` | ⏳ | Operator runs the grep audit |
| No closure routes | ⏳ | Operator runs the grep audit |
| Site remains functional after `config:cache` | ⏳ | Operator smoke-tests after deploy |
| Vite build produces `public/build/manifest.json` | ⏳ | Operator runs `npm run build` and inspects |
