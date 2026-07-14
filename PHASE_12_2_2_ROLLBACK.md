# Phase 12.2 v12.2.2 — Rollback Procedure

Tier 1 revert if the ESLint fix regresses the site settings page.

## Scope

v12.2.2 is a CODE + DOCS release with a single touched code file:

- `resources/js/Pages/Admin/SiteSettings/Index.tsx` (ESLint warning fix + code smell cleanup)

No PHP change. No database migration. No dependency change. No config file change. Rollback is limited to reverting that one TSX file + the VERSION bump.

## When to roll back

- `/admin/site-settings` page fails to render after deploy
- Save button on a settings group stops working (regression from `setData(key, v)` cleanup)
- ESLint warning at line 117 reappears (my fix didn't take hold)
- Any test in the site settings suite regresses

## Tier 1 — Revert the single TSX file

Fastest option. Restore just the modified file from v12.2.1.

### Prerequisites

- The prior approved archive: `marketplace-phase-12-2-1-quality-gate-repair.tar.gz`
- OR Git commit at `phase-12-2-1-quality-gate-repair-reviewed`

### Step 1 — Maintenance mode (optional but recommended)

```bash
sudo -u www-data php artisan down --refresh=15
```

### Step 2 — Restore the file from v12.2.1

Option A — Git:

```bash
cd /var/www/marketplace
sudo -u www-data git checkout phase-12-2-1-quality-gate-repair-reviewed -- \
    resources/js/Pages/Admin/SiteSettings/Index.tsx
```

Option B — Archive extract:

```bash
cd /tmp
tar -xzf /path/to/marketplace-phase-12-2-1-quality-gate-repair.tar.gz \
    marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx
sudo cp marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx \
    /var/www/marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx
sudo chown www-data:www-data /var/www/marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx
```

### Step 3 — Revert VERSION

```bash
echo "Phase 12.2 v12.2.1 Quality Gate Repair" | \
    sudo tee /var/www/marketplace/VERSION > /dev/null
```

### Step 4 — Rebuild frontend

```bash
cd /var/www/marketplace
sudo -u www-data npm run build
```

### Step 5 — Bring back up

```bash
sudo -u www-data php artisan up
```

### Step 6 — Verify

- [ ] `/admin/site-settings` loads
- [ ] Save button on a settings group works
- [ ] `cat VERSION` returns `Phase 12.2 v12.2.1 Quality Gate Repair`

## Tier 2 — Full v12.2.1 rollback (whole archive)

If you want to roll back everything, not just the single file, follow the procedure in `PHASE_12_2_1_ROLLBACK.md`. This drops back to v12.2.1's exact state.

## What NOT to roll back

- **PHP files** — v12.2.2 changed 0 PHP files. Do not revert any PHP file.
- **Database** — no migration in v12.2.2. Do not `migrate:rollback`.
- **`.env`** — no environment changes. Do not touch.
- **Queue / scheduler / cron** — no changes. Do not touch.
- **Vendor intelligence artifacts** — preserved verbatim from v11B.4.3.

## Notes on the ESLint fix

The v12.2.2 change to `SiteSettings/Index.tsx`:

- Added `<Record<string, unknown>>` generic to `useForm` call (line 122)
- Removed unnecessary `as Record<string, unknown>` cast on `data` (line 135)
- Removed two `as never` casts and wrapped `setData(key, v)` in a block (line 141)

Behavior is preserved. If site settings regress after v12.2.2 deploy, the most likely cause is the `setData(key, v)` cleanup — Inertia's `setData` with `Record<string, unknown>` should accept `(string, unknown)` arguments directly, but if Inertia's real type constraints reject it, the pre-cleanup `as never` casts would need to come back. Report the exact error and I'll re-address.

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Tier chosen (1 or 2): _________________________
- Reason: _________________________
- Post-rollback status: _________________________
