# Phase 4 v5.6 — Stability Fix: tsconfig, lazy loading, image visibility

**Targeted fix only.** Same Phase 4 scope. Phase 5 is still not started.

Place Order is confirmed working (per the dev's last report). v5.6 cleans up the remaining stability issues that surfaced once the order flow actually worked end-to-end.

---

## What this addresses — honest mapping

The brief listed six concerns. Here's what I actually found in the v5.5 codebase and what I changed:

| # | Issue | What I found | Action |
|---|---|---|---|
| 1 | `tsconfig.json:23 ignoreDeprecations: "6.0" invalid` | Real. TS 5.5 deprecates `baseUrl` (TS5101); the warning tells you to set `ignoreDeprecations: "6.0"` but TS 5.x only accepts `"5.0"` (it'll be `"6.0"` from TS 6+). The dev followed the misleading TS error message and got TS5103 instead. | Removed `baseUrl` entirely — `paths` works standalone since TS 4.1. Forward-compatible through TS 7, no `ignoreDeprecations` needed. |
| 2 | `useForm` / `ReactNode` / `router` unused imports | **Not present in my v5.5 codebase.** Either the dev re-edited files locally between versions or has stale build cache. My current files pass `tsc --noUnusedLocals` clean. | No code change. Documented cleanup commands. |
| 3 | `usePage<T>() does not satisfy PageProps` | **Already correct in v5.5.** Checkout/Show.tsx uses `usePage<SharedProps>()` and `SharedProps` has `[key: string]: unknown` to satisfy Inertia v2's index-signature constraint + module-augments `@inertiajs/core`'s PageProps. | No code change. |
| 4 | Admin Orders: `Attempted to lazy load [items]` | Real and serious. `OrderResource::getEloquentQuery()` was eager-loading on the list page, but the `ViewOrder` + `EditOrder` Filament pages used default route-model binding which skips that query. `Model::shouldBeStrict` then tripped on `$record->items` access. | Override `resolveRecord` on both pages to use the resource's eager-loaded query; added defensive `loadMissing(['items'])` in `OrderLifecycleService::markShipped`/`markDelivered`/`cancel` so ANY caller is safe. |
| 5 | Product image 403 Forbidden on `/storage/products/admin/...png` | Couldn't reproduce in sandbox; the config + `->visibility('public')` on FileUpload + storage:link were already correct. Cause is almost certainly OS-level (file permissions or PHP built-in server symlink handling). | `storeImages()` now passes `['visibility' => 'public']` explicitly to `$file->store()` so the local driver chmods 0644 regardless of intermediate stages. CI's existing `v5.6 — public-disk uploads are world-readable + no 403` step verifies file mode. Heavy docs on the OS commands. |
| 6 | Filament upload forbidden | Same — couldn't reproduce. The most common real-world cause is APP_URL mismatch breaking Livewire's signed-URL validation, or restrictive perms on `storage/app/livewire-tmp`. | Added `config/livewire.php` with explicit `temporary_file_upload` settings. Documented the APP_URL alignment and chmod commands. |

---

## Files changed

### Production code

| File | Change |
|---|---|
| `tsconfig.json` | Dropped deprecated `baseUrl`. `paths` works standalone since TS 4.1. No TS5101 warning, no `ignoreDeprecations` needed. |
| `app/Filament/Resources/OrderResource/Pages/ViewOrder.php` | Override `resolveRecord` to call `OrderResource::getEloquentQuery()` (which eager-loads `items`, `shippingAddress`, `payments`). |
| `app/Filament/Resources/OrderResource/Pages/EditOrder.php` | Same override. |
| `app/Domain/Order/OrderLifecycleService.php` | `markShipped`, `markDelivered`, `cancel` now call `$order->loadMissing(['items'])` at the top — defence-in-depth. |
| `app/Http/Controllers/Vendor/VendorProductController.php` | `storeImages()` passes `['visibility' => 'public']` so files land with `chmod 0644`. |
| `config/livewire.php` (**new**) | Explicit `temporary_file_upload` config (disk=local, MIMEs jpg/png/webp, 5 MB max, `livewire-tmp` directory). |

### Tests (1 new file, 7 scenarios → 311 total / 43 files)

| File | Scenarios | Covers |
|---|---|---|
| `tests/Feature/LazyLoadingRegressionTest.php` | 7 | `Model::shouldBeStrict(true)` enabled per-test; asserts `OrderResource::getEloquentQuery()` eager-loads items/shippingAddress/payments; `ViewOrder` + `EditOrder` `resolveRecord` return models with items pre-loaded; `OrderLifecycleService::cancel`/`markShipped` work against a fresh model; customer + vendor order detail pages don't lazy-load. |

### CI

- Verdict: `✅ Phase 4 v5.6 PASSES — ready to approve Phase 5`
- `LazyLoadingRegressionTest` added to per-file audit map.
- The existing `v5.6 — public-disk uploads are world-readable + no 403` CI step verifies file modes.
- Cleaned up an orphan reference to `StabilityRegressionTest` (didn't exist).

---

## Required commands after deployment

```bash
# 1. Pull v5.6
tar -xzf marketplace-phase-4-v5.6.tar.gz

# 2. Rebuild + restart Docker
docker compose down
docker compose build app
docker compose up -d

# 3. Inside the container
docker compose exec app bash -lc "
  composer install --no-interaction --no-progress &&
  npm install &&
  npm run typecheck &&
  npm run build &&
  php artisan optimize:clear &&
  php artisan storage:link --force &&
  php artisan migrate:fresh --seed
"

# 4. Linux production / bare-metal — fix storage permissions if you hit 403
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage              -type d -exec chmod 755 {} \;
sudo find storage              -type f -exec chmod 644 {} \;
sudo find storage/app/public   -type d -exec chmod 755 {} \;
sudo find storage/app/public   -type f -exec chmod 644 {} \;
```

### `.env` checklist (Issues 5 + 6)

```env
APP_URL=http://localhost:8000      # MUST match the URL you hit in the browser
FILESYSTEM_DISK=local              # internal/private files
MEDIA_DISK=public                  # product images — switch to 's3' for R2/MinIO in production
```

If your dev URL is `http://127.0.0.1:8000`, set `APP_URL=http://127.0.0.1:8000`. Mismatch between APP_URL and the request URL is the #1 cause of Livewire/Filament upload 403s.

### Windows local dev caveat

`php artisan storage:link` on Windows requires running the terminal **as Administrator** (otherwise the symlink fails silently). Docker setup avoids this entirely:

```cmd
:: In CMD/PowerShell "Run as Administrator"
php artisan storage:link --force
```

---

## Manual developer checklist

| # | Step | Expected |
|---|---|---|
| 1 | `npm install && npm run typecheck` | No errors. No `TS5101` (baseUrl deprecation) or `TS5103` (invalid ignoreDeprecations). |
| 2 | `npm run build` | Vite build completes. |
| 3 | `php artisan optimize:clear && php artisan storage:link --force && php artisan migrate:fresh --seed` | "Demo data ready" banner; `public/storage` exists as a symlink. |
| 4 | Sign in as admin → click **Orders** | List opens, columns including Items count render — **no `lazy load disabled` error**. |
| 5 | Click any order row → view detail | Detail renders with items + shipping address + payments. |
| 6 | Click Edit on the order | Edit form opens, items render. |
| 7 | `/products` then `/products/{slug}` of a demo product | Images appear (the generated SVG demo image). |
| 8 | Open the image URL directly in a new tab | Image renders. If 403, run the chmod commands above. |
| 9 | Admin → Products → Edit → drop a JPG/PNG/WEBP → Save | Upload succeeds. The new image displays on the storefront. |
| 10 | Customer flow: `customer@marketplace.test` → add product → checkout → Place Order | Confirmation page renders. Order shows up in `/orders`, `/vendor/orders`, Admin → Orders. |
| 11 | GitHub Actions | Verdict: **`✅ Phase 4 v5.6 PASSES — ready to approve Phase 5`** with `LazyLoadingRegressionTest` ✅ in the per-file table. |

---

## If you still hit 403 on a product image URL after v5.6

The 403 is OS-level, not code. Walk through these in order:

1. **Symlink exists?** `ls -l public/storage` should print `public/storage -> /…/storage/app/public`. If not: `php artisan storage:link --force` (admin shell on Windows).
2. **File readable?** `ls -l public/storage/products/admin/THE_FILENAME` — owner should be your web user (`www-data` for nginx/apache), perms `-rw-r--r--`. If not: `chmod 644 THE_FILE` and `chown www-data:www-data THE_FILE`.
3. **APP_URL matches request URL?** `.env` `APP_URL=http://localhost:8000` vs visiting `http://127.0.0.1:8000` is a host mismatch that breaks signed URLs in Filament.
4. **PHP built-in server quirk:** `php artisan serve` sometimes refuses symlinked files on certain platforms. Switch to nginx via Docker — `docker-compose.yml` already provides it.
5. **Filament upload 403 specifically:** almost always APP_URL alignment with the actual request URL. Less often: file_uploads = Off in php.ini, or insufficient post_max_size / upload_max_filesize.

---

## What v5.6 deliberately does NOT do

- **Doesn't disable `Model::shouldBeStrict`** — that prevention is the reason this bug was caught early; eager-load instead.
- **Doesn't disable `noUnusedLocals` or other strict TS checks.**
- **Doesn't change the demo data schema** — same as v5.5.
- **Doesn't ship new .env keys** — only the standard `APP_URL` / `MEDIA_DISK` / `FILESYSTEM_DISK`.

---

## Stop discipline

**Phase 5 is not started.** Reply **"approve Phase 5"** only after the 11-step checklist passes and CI is green with the v5.6 verdict.
