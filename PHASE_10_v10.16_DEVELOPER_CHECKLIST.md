# Phase 10 v10.16 — Developer Checklist

Per dev §12 + §13.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.16.tar.gz.sha256
tar -xzf marketplace-phase-10-v10.16.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

## §13 — Required commands

```bash
php artisan optimize:clear
php artisan route:list --path=/                # → GET / → HomeController@index
php artisan test --filter=Home                 # → home-related scenarios pass
php artisan test --filter=Authentication       # → auth scenarios pass
php artisan test --filter=Phase10V1016         # → 20 v10.16 scenarios pass
php artisan test                               # → full suite passes
npm run typecheck                              # MUST PASS — no @ts-ignore added in v10.16
npm run build                                  # MUST SUCCEED
```

Then restart:
```bash
# Ctrl+C the existing artisan serve, then:
php artisan serve
# For PHP-FPM:
sudo systemctl restart php8.3-fpm
```

Hard-refresh the browser (Ctrl+Shift+R). If you use a service worker, unregister it via DevTools → Application → Service Workers.

## §12 — Mandatory manual verification

Open DevTools → Console + Network → Disable cache. Walk through:

- [ ] **A. GET /** as guest. Page renders. **Console has zero errors.**
- [ ] **B. Login as customer.** Redirect to `/`. **Page renders. Console has zero errors.**
- [ ] **C. Refresh `/`** while logged in. Page still renders. Customer is still authenticated.
- [ ] **D. Customer pages**: `/cart`, `/products`, product detail all still open without console errors.
- [ ] **E. Logout.** Guest homepage still renders.
- [ ] **F. Login as vendor.** Redirect to `/vendor`. Vendor dashboard renders.
- [ ] **G. Login as admin** via `/admin/login` (Filament). Admin panel renders.
- [ ] **H. Invalid credentials**: validation message shows.

## §13 — TypeScript verification (additional)

Run `npm run typecheck` from the project root. **Zero errors expected.**

The v10.16 change to `inertia.d.ts` makes `permissions?: string[]` optional. Any other file that does `user.permissions.length` directly will now produce a TypeScript error pointing at the unsafe access — that's the intended safeguard for future code.

If `npm run typecheck` produces errors, those are pre-existing issues in OTHER files that now surface because the type became more restrictive. The fix is to apply the same pattern (`user.permissions ?? []`) at those sites. No `@ts-ignore` allowed per dev §13.

## §1 — If you still see a blank page

```bash
# 1. Confirm v10.16 deployed
cat VERSION                                    # → Phase 10 v10.16

# 2. Confirm fresh build assets
ls -la public/build/manifest.json              # mtime should be after `npm run build`

# 3. Confirm Welcome.tsx in the BUNDLE has the safe pattern
grep -c "user.permissions" public/build/assets/*.js | head
# → should find the bundle but NOT find raw "user.permissions.length"
```

If the browser still loads stale assets, the manifest filenames don't match what's served. Causes:
- Service worker still serving old assets → unregister it
- Reverse proxy / CDN cache → flush it
- Vite dev server running on a different project → stop it
- Browser disk cache → hard-refresh (Ctrl+Shift+R) or DevTools → Network → "Disable cache"

If the bundle IS fresh and the page still goes blank, open DevTools Console. The exception message + stack trace will identify the failing component. v10.16 only fixed the `Welcome.tsx user.permissions.length` crash — a different blank-page bug elsewhere needs its own fix.

## CI verdict

```
✅ Phase 10 v10.16 PASSES — blank homepage runtime repaired
```

**Phase 10 v10.16 STOPS HERE.** No Phase 11. Pending dev runtime verification.
