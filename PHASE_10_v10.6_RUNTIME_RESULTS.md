# Phase 10 v10.6 — Runtime Results

Per dev §5: "Do not claim success if these commands were not executed."

## Commands the dev §5 required

| Command | Status in sandbox | Notes |
|---|---|---|
| `composer install` | ✗ (no network) | dev's environment runs this |
| `npm ci` | ✗ (no network) | dev's environment runs this |
| `php artisan optimize:clear` | ✗ (no PHP) | included in `./scripts/deploy.sh` |
| `php artisan migrate:status` | ✗ (no PHP/MySQL) | no migrations added in v10.6 |
| `php artisan route:list` | ✗ (no PHP) | route table unchanged from v10.5 |
| `php artisan test` | ✗ (no PHP) | 11 new v10.6 Pest scenarios await dev's runtime |
| `npm run typecheck` | ✗ direct (no real packages); ✓ via stubs (tsc exit 0) | dev's CI runs the real packages |
| `npm run build` | ✗ direct; not applicable in sandbox | dev's CI runs this |

## What I CAN claim with evidence

- Real `tsc 6.0.3` against all 9 v10.x React files using v7.7-style hand-written stubs for @inertiajs/react + react: **exit code 0**.
- SHA-256 of every modified file matches between workspace and the shipped archive (see PACKAGE_INTEGRITY).
- All 11 Pest scenarios in `Phase10V106RegressionTest.php` are syntactically valid (Pest's `it()` blocks parse).
- CI YAML is `yaml.safe_load`-able.
- The 4 new CI sub-checks added in v10.6 enforce the fix presence at three layers: config (Defect 1), source markers (Defects 2+3), middleware share (Defect 3).
- The 3 SOURCE FILES the dev's defects pointed to (`config/filesystems.php`, `Show.tsx`, `StorefrontLayout.tsx` + `Catalog/Index.tsx`) all have the exact fix markers present in the shipped archive.

## Per §O final clause

**Phase 10 v10.6 is implemented but requires developer runtime verification.**

I do NOT claim the dev's mandatory command chain has been executed.

## What the dev runs to verify

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.6.tar.gz.sha256
tar -xzf marketplace-phase-10-v10.6.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

`scripts/deploy.sh` (shipped since v10.2) runs: `composer install --no-dev`, `npm ci`, **`npm run build`** (the critical step), `migrate --force`, flushes ALL caches (optimize, route, config, view, cache, filament, permission), rebuilds production caches, restarts the queue, reloads PHP-FPM.

After deploy.sh exits 0:

```bash
php artisan marketplace:verify-fixes   # all ✓
php artisan marketplace:fingerprint    # aggregate must match the canonical in PACKAGE_INTEGRITY
php artisan route:list | grep -E "admin\.vendor-files|vendor\.orders\.(ship|confirm|deliver)"  # 4 lines
```

Then the focused 3-defect browser verification:

1. Sign in as admin → `/admin/vendors` → click any pending vendor → form RENDERS without `Disk [vendors] has no configured driver` → click any document link → file opens (image inline / PDF inline / download depending on type).
2. Sign in as vendor → `/vendor/orders/{id}` → status dropdown visible → pick a valid transition → page reloads with new status (NO `confirm()` dialog appears for the dropdown action; the dropdown disables itself + shows "Updating…" briefly).
3. Open `/products` at 375px viewport → NO categories outside the hamburger → tap hamburger → tap "Categories" → list expands with chevron rotation → tap a category → drawer closes + page filters to that category.

If any of these 3 fails after `deploy.sh` exits 0 AND `verify-fixes` is all ✓ AND `marketplace:fingerprint` matches canonical: share the browser DevTools console output + the failing step number; v10.7 will target that precisely.
