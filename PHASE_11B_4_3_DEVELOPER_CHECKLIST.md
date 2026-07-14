# Phase 11B.4 v11B.4.3 — Developer Checklist

## 1. Backup + branch

```bash
git tag phase-11B-4-2-working-baseline
git checkout -b phase-11B-4-3-final-repair
```

## 2. Extract

```bash
tar -xzf marketplace-phase-11B-4-3-final-vendor-intelligence-repair.tar.gz
sha256sum -c marketplace-phase-11B-4-3-final-vendor-intelligence-repair.tar.gz.sha256
cat VERSION    # → Phase 11B.4 v11B.4.3
```

## 3. Migration

```bash
php artisan migrate:status | grep 2027_01_01
# Expected: Pending

php artisan migrate
# Applies: 2027_01_01_000001_add_vendor_intelligence_digest_columns
# Adds:
#   - vendor_intelligence_summaries.last_digest_sent_at (nullable timestamp)
#   - vendor_intelligence_summaries.email_opted_out (boolean default false)
#   - index vis_last_digest_idx on last_digest_sent_at
```

## 4. Fix 1 verification — admin tab appears

Log in as `admin@marketplace.test`:

1. Navigate to `/admin/site-settings` — expect Vendor Intelligence tab visible in strip
2. Click the tab — form should render with all fields (enabled, thresholds, digest tunables)
3. Change low_stock_threshold from 5 → 10 → Save → success flash
4. Refresh — value persists
5. `php artisan vendor-intelligence:generate` — products with stock 6-10 now flagged low_stock
6. Try low_stock_threshold = -5 → Save → validation error appears in UI

## 5. Fix 2 verification — email digest

Set up the queue worker if not already:
```bash
php artisan queue:work --once     # single-shot for testing
```

1. In admin UI, set Digest emails enabled = ON in Vendor Intelligence tab
2. Ensure Vendor A (`vendor@marketplace.test`) has products with stock=0
3. `php artisan vendor-intelligence:generate --send-emails`
4. Expected output: "Digest jobs dispatched: N"
5. If queue driver = sync, digest sent immediately; if async, run `php artisan queue:work` once
6. Inspect mailer log / mail catcher — Vendor A received the digest
7. `SELECT last_digest_sent_at FROM vendor_intelligence_summaries WHERE vendor_id = <A>` — populated
8. Run generate --send-emails again — no second email (throttled)

Test opt-out:
```sql
UPDATE vendor_intelligence_summaries SET email_opted_out = 1, last_digest_sent_at = NULL WHERE vendor_id = <A>;
```
Then re-run --send-emails — Vendor A gets nothing.

Test master switch:
- Toggle `Digest emails enabled = OFF` in admin UI
- Run --send-emails — no emails dispatched at all

## 6. Fix 3 verification — translation stale

```bash
# Baseline: regenerate a vendor and confirm stale_at is null
php artisan vendor-intelligence:generate --vendor=1

php artisan tinker
>>> \App\Models\ProductTranslation::create([
...     'product_id' => 1,
...     'locale' => 'ar',
...     'field' => 'name',
...     'value' => 'اسم اختبار',
...     'status' => 'pending',
... ]);
>>> exit

# Confirm the vendor was marked stale
mysql> SELECT vendor_id, stale_at, stale_reason FROM vendor_intelligence_summaries WHERE vendor_id = 1;
# Expected: stale_at IS NOT NULL, stale_reason like '%translation%'

# Regenerate only stale vendors
php artisan vendor-intelligence:generate --stale-only

# Confirm cleared
mysql> SELECT stale_at FROM vendor_intelligence_summaries WHERE vendor_id = 1;
# Expected: NULL
```

## 7. Pest suite

```bash
php artisan test --filter=Phase11B43    # 38 scenarios — this release
php artisan test --filter=Phase11B42    # 43 scenarios — v11B.4.2 regression
php artisan test --filter=Phase11B4     # 56 scenarios — v11B.5 regression
php artisan test                        # full suite ~937+
```

## 8. Frontend

```bash
npm ci
npm run typecheck    # expected: no new errors from v11B.4.3 files
npm run build        # produces admin site-settings bundle with Vendor Intelligence tab
```

## 9. Regression checks — v11B.4.2 fixes must still work

- [ ] Pending vendor → /vendor/intelligence still returns 403
- [ ] Approved vendor → /vendor/intelligence returns real numbers with freshness banner
- [ ] `php artisan schedule:list | grep vendor-intelligence` shows both entries
- [ ] Vendor with variants → variant alerts still fire
- [ ] Product update still marks stale via ProductObserver (independent of the new translation observer)
- [ ] Vendor Report embed still visible on /vendor/reports
- [ ] Product Quality Badge still visible on /vendor/products/{id}/edit
- [ ] Admin overview /admin/vendor-intelligence still loads

## 10. Sign-off criteria

Only mark v11B.4.3 approved once:
- [ ] Migration `2027_01_01` applied without error
- [ ] Vendor Intelligence tab visible in admin site settings and value persists after save
- [ ] Vendor with alerts receives digest email; suspended/pending/no-alerts vendors do not
- [ ] Duplicate `--send-emails` within throttle window does not duplicate email
- [ ] Product translation approve/create/delete via Filament sets `stale_at` on the vendor's summary
- [ ] `--stale-only` regenerates only affected vendors
- [ ] `php artisan test --filter=Phase11B43` shows 38/38 pass
- [ ] `php artisan test --filter=Phase11B42` still shows 43/43 pass (regression)
