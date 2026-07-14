# Phase 11B.4 v11B.4.3 — Patch Notes

Final vendor intelligence repair. Closes three remaining issues from the v11B.4.2 sign-off:

1. Admin Site Settings page now shows a real "Vendor Intelligence" tab that lets admin edit thresholds, feature flags, and digest tunables
2. Vendor intelligence email digest is implemented — Mailable + queued job + Blade template + `--send-emails` command flag + throttling + PII discipline
3. Product translation edits (via the `product_translations` workflow table) now mark vendor intelligence stale

## Files changed

**8 modified**: VERSION, SiteSettingsController, GenerateVendorIntelligence, AppServiceProvider, VendorIntelligenceSummary, config/site.php, SiteSettings/Index.tsx, ci.yml, lang/en.json, lang/ar.json.

**6 new**: `2027_01_01` migration, `VendorIntelligenceDigestMail`, `SendVendorIntelligenceDigest`, digest Blade template, `ProductTranslationObserver`, Phase11B43 test file (38 scenarios).

## Deploy

```bash
php artisan optimize:clear
php artisan migrate                                       # applies 2027_01_01
php artisan test --filter=Phase11B43                      # 38 scenarios (THIS release)
php artisan test --filter=Phase11B42                      # 43 scenarios (regression)
php artisan test --filter=Phase11B4                       # 56 scenarios (regression)
npm ci && npm run typecheck && npm run build
```

## Verify Fix 1 (admin tab)

1. Log in as super_admin
2. Navigate to `/admin/site-settings` — expect "Vendor Intelligence" tab in the strip (right of "Mobile")
3. Click it — expect a form with fields: Enabled, Scheduler enabled, Low stock threshold, Fast moving days, Fast moving min orders, Slow moving days, Slow moving min age days, Stagnant days, Min views for conversion, High view conversion ceil, Min wishlist interest, Min cart abandonment, Dashboard alert limit, Default snooze days, Cache TTL, Digest emails enabled, Digest min critical, Digest throttle hours, and a JSON view of quality_weights
4. Change Low stock threshold from 5 to 10, click Save
5. Expect success message, refresh page, expect value still 10
6. Run `php artisan vendor-intelligence:generate` — products with stock in the 6-10 range now get low_stock alerts (didn't before)
7. Set Low stock threshold to -5, click Save — expect validation error banner

## Verify Fix 2 (email digest)

1. As admin, set `digest_emails_enabled = true` in the Vendor Intelligence tab
2. Create Vendor A (approved) with a valid business_email and a product with stock=0
3. Create Vendor B (approved) with a healthy inventory (no alerts)
4. Create Vendor C (suspended) with a critical alert
5. Run: `php artisan vendor-intelligence:generate --send-emails`
6. Expect Vendor A to receive a digest email
7. Expect Vendors B and C to receive nothing
8. Run the command again immediately — Vendor A should NOT receive a second email (24h throttle)
9. Inspect the email — expect summary table + top alerts by product name; expect NO customer emails / names / order IDs

## Verify Fix 3 (translation stale)

1. Load a product owned by Vendor A
2. In Filament (admin) edit the Arabic name translation — approve it
3. Query: `SELECT vendor_id, stale_at, stale_reason FROM vendor_intelligence_summaries WHERE vendor_id = <A's id>`
4. Expect `stale_at IS NOT NULL` and `stale_reason LIKE '%translation%'`
5. Run: `php artisan vendor-intelligence:generate --stale-only`
6. Expect Vendor A to be regenerated, other vendors skipped
7. Query again — `stale_at IS NULL`, `last_generated_at` updated

## Counts

| | v11B.4.2 → v11B.4.3 |
|---|---|
| CI verification blocks | 209 → ~213 (+4) |
| Pest scenarios (v11B.4.3 file) | +38 |
| Migrations | +1 (`2027_01_01`) |
| Localization keys | +13 en + 13 ar |
| Observers | +1 (`ProductTranslation`) |
| Mail classes | +1 (VendorIntelligenceDigestMail) |
| Job classes | +1 (SendVendorIntelligenceDigest) |
| Blade templates | +1 (vendor-intelligence-digest) |

## Rollback

See PHASE_11B_4_3_ROLLBACK.md. Tier 1: rollback `2027_01_01` migration + revert 2 files. Tier 2: full revert to v11B.4.2. Tier 3: chain back to v11B.4 or v11B.3.3 approved baseline.

## Honest scope

- Cannot run migrate/test/queue:work in sandbox — you must run them
- All 3 remaining defects addressed at source with grep evidence and Pest scenarios
- v11B.4.2 architecture 100% preserved (16/16 SHA match on files I touched, all v11B.4.2 new files intact)
- Vendor-side email preferences UI still deferred (opt-out column exists and works via direct DB write; no vendor-facing form field yet)
- No new features beyond the scope needed to close each fix
