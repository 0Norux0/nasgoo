# Phase 12.2 v12.2.3 — Rollback Procedure

Tier 1 revert if the SiteSettings type change regresses the admin settings page.

## Scope

v12.2.3 is a CODE + DOCS release with a **single touched code file**:

- `resources/js/Pages/Admin/SiteSettings/Index.tsx` (added JsonValue types + updated the settings type chain)

No PHP change. No database migration. No route change. No config change. Rollback is limited to reverting that one TSX file + the VERSION bump.

## When to roll back

- `/admin/site-settings` page fails to render after deploy
- Save button on a settings group stops working (regression from the type chain change)
- ESLint warning at line 122 (now 132) reappears (my fix didn't take hold)
- Any test in the site settings suite regresses

## Tier 1 — Revert the single TSX file

Fastest option. Restore just the modified file from v12.2.2.

### Prerequisites

- The prior approved archive: `marketplace-phase-12-2-2-final-lint-format-repair.tar.gz`
- OR Git commit at `phase-12-2-2-final-lint-format-repair-reviewed`

### Step 1 — Maintenance mode (optional but recommended)

```bash
sudo -u www-data php artisan down --refresh=15
```

### Step 2 — Restore the file from v12.2.2

Option A — Git:

```bash
cd /var/www/marketplace
sudo -u www-data git checkout phase-12-2-2-final-lint-format-repair-reviewed -- \
    resources/js/Pages/Admin/SiteSettings/Index.tsx
```

Option B — Archive extract:

```bash
cd /tmp
tar -xzf /path/to/marketplace-phase-12-2-2-final-lint-format-repair.tar.gz \
    marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx
sudo cp marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx \
    /var/www/marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx
sudo chown www-data:www-data /var/www/marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx
```

### Step 3 — Revert VERSION

```bash
echo "Phase 12.2 v12.2.2 Final Lint and Format Repair" | \
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
- [ ] `cat VERSION` returns `Phase 12.2 v12.2.2 Final Lint and Format Repair`

## Tier 2 — Full v12.2.2 rollback (whole archive)

If you want everything rolled back, not just the single file:

Follow the procedure in `PHASE_12_2_2_ROLLBACK.md`. That drops back to v12.2.2's exact state — you also lose these 5 v12.2.3 docs (which is fine, they document work that's now reverted).

## What NOT to roll back

- **PHP files** — v12.2.3 changed 0 PHP files. Do not revert any PHP file.
- **Database** — no migration in v12.2.3. Do not `migrate:rollback`.
- **`.env`** — no environment changes. Do not touch.
- **Queue / scheduler / cron** — no changes.
- **Vendor intelligence, personalization, checkout, orders** — all preserved verbatim.

## Notes on the type change

The v12.2.3 change is purely a TypeScript-level improvement. The runtime behavior of the SiteSettings page is identical to v12.2.2:

- Same fields render
- Same values are POSTed to `/admin/site-settings/{group}`
- Same reset action works
- Same tabs are visible (branding through vendor_intelligence)

Only the compile-time types changed. If runtime behavior regresses after v12.2.3 deploy, the cause is not the type change — it's likely a bad build (`npm run build` didn't run against the new types) or a stale bundle cached in the browser. Try a hard-refresh first, then check `public/build/manifest.json` timestamp.

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Tier chosen (1 or 2): _________________________
- Reason: _________________________
- Post-rollback status: _________________________
