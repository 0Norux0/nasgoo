# Phase 12.3 v12.3.2 — Rollback Procedure

Tier 1 revert if any v12.3.2 change regresses. Nothing in v12.3.2 changes license security logic — it's a diagnostic command + doc + formatting-only rewrites — so rollback impact is minimal.

## Scope

v12.3.2 touches:

- **NEW** `app/Console/Commands/LicenseDoctorCommand.php` — preflight diagnostic
- **NEW** `PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md` (root)
- **Modified** `resources/js/Pages/Admin/License/Index.tsx` — Prettier-compliant rewrite
- **Modified** `resources/js/Pages/License/Status.tsx` — Prettier-compliant rewrite
- **Modified** `resources/js/Pages/License/Blocked.tsx` — Prettier-compliant rewrite
- **Modified** `.env.testing.example` — updated intro
- `VERSION` bumped
- 5 new `PHASE_12_3_2_*.md` docs

No security change. No test change. No migration change. No route change. No dependency change.

## When to roll back

- The rewritten License pages have a visual/functional regression not caught in dev review (very unlikely — it's a stylistic reformat, not a logic change)
- The `license:doctor` command produces a fatal error (also unlikely — it's guarded with try/catch everywhere)
- You need to align to a downstream fork that hasn't picked up v12.3.2

**Do NOT roll back for these reasons:**
- Prettier `format:check` still fails after `npm run format` — that's environment, and Tier 1 rollback makes it WORSE
- Tests still fail before assertions — that's the environment gap `license:doctor` is designed to expose; rolling back removes your diagnostic

## Tier 1 — Revert the touched files to v12.3.1

Fastest option. Restore the 4 modified files + delete the 2 new ones.

### Prerequisites

- Prior approved archive: `marketplace-phase-12-3-1-license-repair.tar.gz`
- OR Git commit at `phase-12-3-1-license-repair`

### Step 1 — Maintenance mode (recommended)

```bash
sudo -u www-data php artisan down --refresh=15
```

### Step 2 — Restore the 4 modified files from v12.3.1

Option A — Git:

```bash
cd /var/www/marketplace
sudo -u www-data git checkout phase-12-3-1-license-repair -- \
    resources/js/Pages/Admin/License/Index.tsx \
    resources/js/Pages/License/Status.tsx \
    resources/js/Pages/License/Blocked.tsx \
    .env.testing.example
```

Option B — Archive extract:

```bash
cd /tmp
tar -xzf /path/to/marketplace-phase-12-3-1-license-repair.tar.gz \
    marketplace/resources/js/Pages/Admin/License/Index.tsx \
    marketplace/resources/js/Pages/License/Status.tsx \
    marketplace/resources/js/Pages/License/Blocked.tsx \
    marketplace/.env.testing.example

for f in \
    resources/js/Pages/Admin/License/Index.tsx \
    resources/js/Pages/License/Status.tsx \
    resources/js/Pages/License/Blocked.tsx \
    .env.testing.example; do
    sudo cp "marketplace/$f" "/var/www/marketplace/$f"
    sudo chown www-data:www-data "/var/www/marketplace/$f"
done
```

### Step 3 — Delete the 2 new files

```bash
sudo rm -f /var/www/marketplace/app/Console/Commands/LicenseDoctorCommand.php
sudo rm -f /var/www/marketplace/PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md
```

### Step 4 — Revert VERSION

```bash
echo "Phase 12.3 v12.3.1 License Repair" | sudo tee /var/www/marketplace/VERSION > /dev/null
```

### Step 5 — Rebuild caches + frontend

```bash
cd /var/www/marketplace
sudo -u www-data composer dump-autoload -o
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data npm run build
```

### Step 6 — Bring back up

```bash
sudo -u www-data php artisan up
```

### Step 7 — Verify

- [ ] `cat VERSION` returns `Phase 12.3 v12.3.1 License Repair`
- [ ] `/admin/license` loads
- [ ] `php artisan license:doctor` returns "Command not defined" (expected — deleted)
- [ ] Existing license activations still work

## What Tier 1 rollback loses

- **`license:doctor` diagnostic**: gone. Missing extensions become cryptic runtime errors again instead of clear FAIL rows with install commands.
- **Test environment guide**: gone. Developer must re-derive the pdo_pgsql / sodium install steps.
- **Rewritten License page formatting**: reverts to the pre-Prettier state. Those 3 files will fail `format:check` again.
- **`.env.testing.example` clarifications**: intro reverts to generic v12.3.1 wording (SQLite still default; still functional).

Nothing security-related. No test scenario impact. No route or DB impact.

## Tier 2 — Selectively keep or drop pieces

If only ONE part of v12.3.2 caused trouble:

- **Doctor command misbehaving**: `rm app/Console/Commands/LicenseDoctorCommand.php` and re-run `composer dump-autoload -o`. Keep the 3 license page rewrites.
- **A specific rewritten license page has a visual regression**: `git checkout phase-12-3-1-license-repair -- resources/js/Pages/{path}` for just that file.

Tier 2 lets you keep v12.3.2 benefits while surgically reverting a specific piece.

## Tier 3 — Full stack rollback to Phase 12.3 (drops v12.3.1 too)

See `PHASE_12_3_ROLLBACK.md` (from Phase 12.3). Only if you want to un-do the v12.3.1 security fixes AND v12.3.2 tooling AND the entire Phase 12.3 license layer.

**Warning**: this re-opens the v12.3.1 fingerprint bypass and APP_URL-based domain drift. Not recommended without a specific security justification.

## What NOT to roll back

- Any Phase 12.3 file (`LicenseVerifier`, `LicenseManager`, `LicenseDomainResolver`, `EnsureValidLicense`, `LicenseController`, migrations, routes, config).
- `bootstrap/app.php` — v12.3.2 didn't touch it.
- `phpunit.xml` — v12.3.2 didn't touch it.
- Test file — v12.3.2 didn't touch it (34 scenarios preserved).
- Any active license activation row.
- `storage/app/license/installation_id`.

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Tier chosen: _________________________
- Reason for rollback: _________________________
- Post-rollback verification passed: [ ]
