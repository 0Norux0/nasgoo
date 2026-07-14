# Phase 11B.4 v11B.4.2 — Developer Checklist

## 1. Backup

```bash
git tag phase-11B-5-baseline    # if you'd been running v11B.5
git checkout -b phase-11B-4-2-mandatory-repair
```

## 2. Extract

```bash
tar -xzf marketplace-phase-11B-4-2-mandatory-vendor-intelligence-repair.tar.gz
```

## 3. VERSION + integrity

```bash
cat VERSION                          # → Phase 11B.4 v11B.4.2
sha256sum -c marketplace-phase-11B-4-2-mandatory-vendor-intelligence-repair.tar.gz.sha256
```

## 4. Migration

```bash
php artisan migrate:status | grep 2026_12_01
# Expected: 2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale = Pending

php artisan migrate
# Expected: 1 migration ran
# The migration:
#   1. Adds active_dedupe_key column to vendor_intelligence_alerts
#   2. Backfills the key for active/snoozed/dismissed rows
#   3. Resolves any pre-existing duplicate rows (keeps newest, nullifies rest)
#   4. Adds UNIQUE INDEX via_active_dedupe_uniq
#   5. Adds stale_at + stale_reason + last_generated_at to summaries
#   6. Adds index on stale_at

php artisan migrate:status | grep 2026_12_01
# Expected: = Ran
```

## 5. Scheduler check (Defect 2 proof)

```bash
php artisan schedule:list | grep vendor-intelligence
# Expected TWO entries:
#   0 * * * *  php artisan vendor-intelligence:generate --stale-only
#   0 3 * * *  php artisan vendor-intelligence:prune
```

## 6. Generate initial data

```bash
php artisan vendor-intelligence:generate
# Expected: "Processed N vendors" where N = # of approved vendors
```

## 7. Pest suite

```bash
php artisan test --filter=Phase11B42
# Expected: 43 passing scenarios covering all 11 defects

php artisan test --filter=Phase11B4
# Expected: 56 passing (v11B.5 regression suite)

php artisan test
# Expected: all ~899 scenarios passing
```

## 8. Frontend

```bash
npm ci
npm run typecheck    # expected: 0 new errors from v11B.4.2 files
npm run build
```

## 9. Browser walkthrough

Per §14 of directive, verify each of the 11 defects manually:

**Defect 1** — Authorization
- Log in as pending-vendor → `/vendor/intelligence` → 403
- Log in as suspended-vendor → `/vendor/intelligence` → 403
- Log in as approved vendor → `/vendor/intelligence` → 200 with real data

**Defect 2** — Scheduler
- `php artisan schedule:list` shows both vendor-intelligence commands
- Wait an hour (or run `php artisan schedule:run` manually) — generate command fires

**Defect 3** — Admin settings
- Log in as super_admin
- POST `/admin/site-settings/vendor_intelligence` with `{"low_stock_threshold": 7}` → 302 + persisted
- Reload → new value active

**Defect 4** — Enabled flag
- Toggle `enabled=false` via admin
- Reload `/vendor` → panel shows disabled banner with PauseCircle
- POST `/vendor/intelligence/dismiss` → 403
- Run `php artisan vendor-intelligence:generate` → warns + exits 0 (no data change)
- Run `php artisan vendor-intelligence:generate --vendor=1 --force` → still runs

**Defect 5** — DB UNIQUE
- Verify UNIQUE index exists: `SHOW INDEX FROM vendor_intelligence_alerts WHERE Key_name = 'via_active_dedupe_uniq'`
- Try to insert a duplicate active alert manually → MySQL rejects with error 1062

**Defect 6** — Variant alerts
- Create a product with variants; set one variant stock=0, is_active=1
- Run `php artisan vendor-intelligence:generate --vendor=X`
- `/vendor/intelligence` → variant_out_of_stock alert appears with variant label + product name

**Defect 7** — Search demand
- Simulate: insert into search_queries with query='<something not in your vendor's products>', locale='en', search_count=500
- Regenerate → search_demand suggestion appears

**Defect 8** — Report embed
- Load `/vendor/reports` → "Intelligence insights" card at top with 4 metric cells

**Defect 9** — Product-edit badge
- Load `/vendor/products/{id}/edit` → quality badge above form
- Fresh product without generation → shows "not yet calculated" state

**Defect 10** — Email
- Verify vendor Settings has no email preferences UI (deferred, correct)

**Defect 11** — Stale marking
- Update a product's stock → check `vendor_intelligence_summaries.stale_at` for that vendor → not null
- Run `php artisan vendor-intelligence:generate --stale-only` → only that vendor is regenerated
- Reload `/vendor` panel → freshness banner shows "Last refreshed: <now>"

## 10. Sign-off criteria

Only mark v11B.4.2 approved once:
- [ ] Migration applied without error
- [ ] `php artisan schedule:list` shows both entries
- [ ] `php artisan test --filter=Phase11B42` shows 43/43 pass
- [ ] All 11 browser scenarios above verified
- [ ] Vendor A cannot affect Vendor B's data via crafted requests (checked in §Defect5)
