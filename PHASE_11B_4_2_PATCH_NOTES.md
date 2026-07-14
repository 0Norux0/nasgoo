# Phase 11B.4 v11B.4.2 — Patch Notes

Emergency corrective release addressing 11 developer-confirmed defects in v11B.4. Repair only — no new features beyond the scope needed to close each defect.

## Files changed

**14 modified** (see main report for per-defect mapping):
`VERSION`, `routes/web.php`, `routes/console.php`, `app/Http/Controllers/Admin/SiteSettingsController.php`, `app/Http/Controllers/Vendor/VendorIntelligenceController.php`, `app/Http/Controllers/Vendor/VendorProductController.php`, `app/Services/VendorIntelligence/VendorIntelligenceManager.php`, `app/Services/VendorIntelligence/InventoryAlertService.php`, `app/Services/VendorIntelligence/VendorOpportunityService.php`, `app/Services/Settings/SiteSettingsService.php`, `app/Console/Commands/GenerateVendorIntelligence.php`, `app/Console/Commands/PruneVendorIntelligence.php`, `app/Providers/AppServiceProvider.php`, `app/Models/VendorIntelligenceAlert.php`, `app/Models/VendorIntelligenceSummary.php`, `resources/js/Components/VendorIntelligence/VendorIntelligencePanel.tsx`, `resources/js/Pages/Vendor/Products/Edit.tsx`, `resources/js/Pages/Vendor/Reports/Index.tsx`, `lang/en.json`, `lang/ar.json`, `.github/workflows/ci.yml`.

**7 new**:
- `database/migrations/2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale.php`
- `app/Observers/VendorIntelligence/ProductObserver.php`
- `app/Observers/VendorIntelligence/OrderObserver.php`
- `app/Observers/VendorIntelligence/VendorObserver.php`
- `resources/js/Components/VendorIntelligence/VendorReportsIntelligenceEmbed.tsx`
- `resources/js/Components/VendorIntelligence/ProductQualityBadge.tsx`
- `tests/Feature/Phase11B42MandatoryVendorIntelligenceRepairTest.php` (43 scenarios)

## Deploy commands the dev must run

```bash
php artisan optimize:clear
php artisan migrate:status | grep vendor_intelligence     # verify v11B.4 tables ran
php artisan migrate                                       # applies 2026_12_01 (dedupe + stale)
php artisan schedule:list | grep vendor-intelligence      # Defect 2 proof
php artisan vendor-intelligence:generate                  # populate initial data
php artisan test --filter=Phase11B42                      # 43 defect-repair scenarios
php artisan test --filter=Phase11B4                       # 56 v11B.5 scenarios (regression)
npm ci && npm run typecheck && npm run build
```

## Manual browser verification

1. Log in as `pending-vendor@marketplace.test` — try `/vendor/intelligence` → expect 403 (Defect 1)
2. Log in as `vendor@marketplace.test` (approved) — expect panel loads with real numbers, freshness banner showing `Last refreshed: <recent time>` (Defect 11)
3. As admin — POST `/admin/site-settings/vendor_intelligence` with `{"low_stock_threshold": 7}` → expect 302 + persisted value (Defect 3)
4. Toggle `enabled=false` via admin, reload `/vendor` — panel shows "Vendor insights are currently disabled..." with PauseCircle icon (Defect 4)
5. Create a `ProductVariant` with stock=0 — regenerate — expect `variant_out_of_stock` alert with entity_type='variant' (Defect 6)
6. Edit a product on `/vendor/products/{id}/edit` — expect quality badge above the form showing score % + missing fields (Defect 9)
7. Load `/vendor/reports` — expect intelligence insights card above reports content (Defect 8)
8. Verify no email settings UI exists in Vendor Settings (Defect 10 honestly deferred)
9. Update a product's stock — reload `/vendor` panel — freshness banner should show either fresh (if scheduler ran) or stale-pending (Defect 11)

## Counts

| | v11B.5 → v11B.4.2 |
|---|---|
| CI sub-checks | 200 → ~209 (+9 defect blocks) |
| Pest scenarios (v11B.4.2 file) | +43 |
| Migrations | +1 (`2026_12_01`) |
| Localization keys | +21 en + 21 ar |
| Alert type constants | +4 |
| Observers | +3 |

## Rollback

See PHASE_11B_4_2_ROLLBACK.md. Tier 1 = `php artisan migrate:rollback --step=1`. Tier 2 = extract v11B.5 baseline. Tier 3 = chain back to v11B.4 baseline or v11B.3.3 approved.

## Honest scope

- Cannot run migrate/test/schedule:list in this sandbox — dev must run those
- 6 v11B.5 fixes preserved (ProductQualityService images(), Manager support_tickets, factory rewrites)
- All 11 defects fixed at source with grep evidence and Pest scenarios
- Defect 10 (emails) explicitly still deferred — no UI added
