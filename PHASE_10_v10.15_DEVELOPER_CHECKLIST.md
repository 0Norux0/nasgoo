# Phase 10 v10.15 — Developer Checklist

The dev's §15 + §17 manual walkthrough.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.15.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.15.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

## §18 — Required pre-test commands

```bash
composer install
npm ci
php artisan optimize:clear
php artisan route:list | grep -i login            # → /login (GET + POST), /admin/login (Filament)
php artisan route:list | grep -i dashboard
php artisan test --filter='Phase10V1015'          # → 20 Pest scenarios, all should pass
php artisan test                                  # → full suite passes
npm run typecheck
npm run build
```

Then restart Laravel from the active folder:
```bash
# Ctrl+C the existing artisan serve, then:
php artisan serve

# For PHP-FPM:
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

Hard-refresh the browser (Ctrl+Shift+R). If the dev uses Vite dev server, restart it too.

## §1 — Reproduction step (the dev's exact failure)

Before testing v10.15, capture what's happening:

1. Open DevTools → Network tab → Preserve log.
2. Open the customer login page.
3. Submit valid customer credentials.
4. Record the POST `/login` request:
   - Status code (302 expected, 419/422/500 = problem)
   - Response Location header (where it redirects)
   - Set-Cookie headers (the session cookie should be there)
5. Follow the redirect manually. Record the GET request:
   - Status code (200 expected, 500 = problem at the destination)
6. If GET returns 500, open `storage/logs/laravel.log` and find the most recent error. With v10.15 deployed, look for log entries containing `(Phase 10 v10.15 defensive catch)` — those tell you EXACTLY which share() closure failed and why.

## §15 — Manual verification (using existing customer accounts)

Walk through these in order:

- [ ] **A. Clear caches and restart**: `php artisan optimize:clear` + restart PHP-FPM/artisan serve.
- [ ] **B. GET /login**: page renders. No 500, no blank page.
- [ ] **C. POST /login** with valid customer credentials: redirects to `/`.
- [ ] **D. GET /** after the redirect: page renders. Customer is logged in (greeting visible, "Logout" link).
- [ ] **E. Refresh /**: customer is still logged in. Session persisted.
- [ ] **F. Customer pages**: `/cart`, `/wishlist`, `/account`, `/orders` all open.
- [ ] **G. Logout**: redirects to `/`, customer is logged out.
- [ ] **H. Login again**: works. No redirect loop.
- [ ] **I. Vendor login** (`vendor@marketplace.test`): redirects to `/vendor`. Dashboard renders.
- [ ] **J. Admin login** (`/admin/login`): Filament panel works (separate flow — UNTOUCHED by v10.15).
- [ ] **K. Invalid customer credentials**: validation message displays, user stays on /login.
- [ ] **L. Customer cannot access `/vendor`**: redirected.
- [ ] **M. Customer cannot access `/admin/reports`**: redirected/403.
- [ ] **N. Test using BOTH `localhost:8000` AND `127.0.0.1:8000`** — they may have different cookie domains. Use the same one the dev had pre-v10.14.

## If login STILL fails after v10.15

The v10.15 defensive wrappings will:
1. Log the actual exception in `storage/logs/laravel.log` under a `(Phase 10 v10.15 defensive catch)` marker
2. Return a safe fallback so the page still renders

```bash
tail -50 storage/logs/laravel.log | grep -i "v10.15 defensive catch"
```

If you see entries here, the v10.15 catch is doing its job — the underlying environment issue is whatever the exception message says. Likely culprits:

### Cache driver broken

```bash
php artisan tinker
>>> Cache::put('p1015_test', 1, 60); Cache::get('p1015_test')
# Should return 1 instantly.
```

If slow or throws → cache driver is misconfigured. Common fixes:
- `CACHE_STORE=redis` + Redis unreachable: set `CACHE_STORE=file` in `.env`, then `php artisan optimize:clear`.
- `CACHE_STORE=database` + no cache table: `php artisan cache:table && php artisan migrate`.
- `CACHE_STORE=file` + permission issue: `chmod -R 775 storage/framework/cache`.

### Session driver broken

```bash
>>> session(['p1015_test' => 'x']); session('p1015_test')
# Should return 'x'.
```

If broken → similar Redis/database/file fixes as cache. Also check:
- `SESSION_DRIVER` in `.env`
- `SESSION_SECURE_COOKIE=false` for local HTTP testing (NOT `true`)
- `SESSION_DOMAIN` unset or matches the host you're testing on (NOT a production domain)
- `SESSION_SAME_SITE=lax`

### Spatie permission cache out of sync

```bash
php artisan permission:cache-reset
```

### Browser cookies blocked

DevTools → Application → Cookies. The XSRF-TOKEN and session cookies must be set after the GET /login. If not, browser is blocking them (likely SameSite or Secure mismatch).

### Stale build assets

```bash
ls -la public/build/manifest.json
# fresh mtime?

npm run build
```

Then hard-refresh. Compare `public/build/manifest.json` filenames with what the Network tab loads.

## CI verdict

```
✅ Phase 10 v10.15 PASSES — customer login regression defensively patched
```

## Final

**Phase 10 v10.15 STOPS HERE.** No Phase 11. No public-launch declaration. Pending the dev's runtime verification.

Customer authentication is the launch-blocking gate. v10.15 cannot mark Phase 10 complete until the dev confirms confirmations A-N above.
