# Phase 2 — v3.1 Patch Notes

**Type:** Asset-loading fix package. No scope changes from v3.0.
**Built on:** Phase 2 v3.0 (functional, but visually broken in admin)
**Status:** Drop-in replacement — overwrite your existing files, push, verify.

---

## What was wrong

The admin UI rendered with default browser styling (plain inputs, blue underlined links, oversized icons, no layout). Login + auth worked, so it was clearly an **asset-loading** problem, not a backend bug.

Root-cause was a chain of seven small misconfigurations that compounded:

| # | Bug | Effect |
|---|---|---|
| 1 | `composer.json` post-autoload-dump didn't run `filament:upgrade` | Filament's CSS/JS never copied from `vendor/` to `public/` |
| 2 | Dockerfile used `composer install --no-scripts` and never published Filament | Production image had no Filament assets |
| 3 | Docker dev mode bind-mounts host source over `/var/www/html`, shadowing the published Filament assets that *would* have been in the image | Even a correct image gets broken at run-time |
| 4 | `entrypoint.sh` had no asset-publish safety net | No recovery from #3 |
| 5 | `app.blade.php` used `@routes` but Ziggy was not installed | Latent: would print literal "@routes" on any Inertia page |
| 6 | `@vite(['…', "resources/js/Pages/{$page['component']}.tsx"])` referenced a dynamic page path | Brittle preload — only worked when the manifest indexed it under that exact key |
| 7 | CI never verified `public/build/manifest.json` or `public/css/filament/` existed after build | CI passed green even with broken assets |

---

## What v3.1 changes

| File | Change |
|---|---|
| `composer.json` | Adds `@php artisan filament:upgrade` to `post-autoload-dump`. Every future `composer install` now publishes Filament's CSS/JS automatically. |
| `resources/views/app.blade.php` | Removes `@routes` (Ziggy not installed; we use Laravel's named routes from React via Inertia link helpers, not `route()`). Simplifies `@vite()` to just the two declared entry points; per-page bundles are still code-split via `import.meta.glob` inside `app.tsx`. |
| `Dockerfile` | After `COPY vendor` in both the production *and* dev stages, runs `php artisan package:discover && php artisan filament:upgrade` explicitly. (Composer was called with `--no-scripts` for build reproducibility.) |
| `docker/entrypoint.sh` | If `public/css/filament/` or `public/js/filament/` is missing at container start, runs `php artisan filament:upgrade`. Also warns when `public/build/manifest.json` is missing so the developer knows to run `npm run build` or `npm run dev`. |
| `.github/workflows/ci.yml` | Two new fail-loud verification steps:<br>• **After `composer install`** — confirms `public/css/filament/` and `public/js/filament/` exist. If not, fails with a clear error.<br>• **After `npm run build`** — confirms `public/build/manifest.json` exists and contains entries for `resources/css/app.css` and `resources/js/app.tsx`. |
| `TROUBLESHOOTING.md` *(new)* | Step-by-step diagnostic for the next time something like this happens. |

---

## What v3.1 does **NOT** change

- No new tables, models, controllers, or React components
- No business logic, no auth, no Phase 2 scope changes
- No new npm or composer dependencies
- The Phase 2 verdict remains: **ready to approve Phase 3** once these assets verify

---

## How to apply

1. **Replace your Phase 2 v3.0 files with v3.1.** The simplest way: extract `marketplace-phase-2-v3.1.tar.gz` over the existing checkout, then `git add -A && git commit -m "Phase 2 v3.1 — asset loading fix"`.

2. **Trigger Generate Lock Files** workflow.
   *Not strictly required* — no new dependencies. But `composer.lock` will still want to refresh because the `scripts` section changed (Composer hashes the entire `composer.json` content). Running it costs you nothing.

3. **Rebuild the Docker image** if you're testing in Docker:
   ```bash
   docker compose down
   docker compose build --no-cache app
   docker compose up -d
   ```
   The `--no-cache` is the important bit — without it Docker reuses the old layers and your fix doesn't get baked in.

4. **Or just run the two commands inside a running container** to fix without rebuilding:
   ```bash
   docker compose exec app php artisan filament:upgrade
   docker compose exec app npm run build       # or `npm run dev` in another terminal
   ```

5. **Hard-refresh the admin page** (Cmd/Ctrl-Shift-R).

---

## Verification — CI

The CI summary should now show two new ticks:

```
✅ public/css/filament exists (N files)
✅ public/js/filament  exists (N files)
✅ public/build/manifest.json exists (M bytes)
✅ Entries indexed: K
  ✓ resources/css/app.css  → assets/app-<hash>.css
  ✓ resources/js/app.tsx   → assets/app-<hash>.js
```

If either fails, CI fails loudly **before** you get a misleading green deploy.

---

## Verification — manual

Inside your container or Codespace, run the asset health check from `TROUBLESHOOTING.md §10`:

```bash
[ -f public/build/manifest.json ]  && echo ✓ Vite manifest    || echo ✗ run npm run build
[ -d public/css/filament ]         && echo ✓ Filament CSS     || echo ✗ run php artisan filament:upgrade
[ -d public/js/filament ]          && echo ✓ Filament JS      || echo ✗ run php artisan filament:upgrade
```

All three should be `✓`. Then open `/admin` in your browser and **hard-refresh**.

---

## What's next

Same as v3.0: reply **"approve Phase 3"** only after you have a green CI + a visually correct admin panel. Phase 3 (product catalog) remains untouched and is not started.
