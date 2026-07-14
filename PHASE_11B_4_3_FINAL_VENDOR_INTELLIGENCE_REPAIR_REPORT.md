# Phase 11B.4 v11B.4.3 — Final Vendor Intelligence Repair

Per your directive §11. Three narrow fixes; no rewrites; v11B.4.2 preserved.

## Sandbox constraint declaration

Same as prior releases: no PHP, no MySQL, no Docker, no HTTP server. What I did:

- Cross-referenced every claim against actual source files
- Wrote real code fixes at exact source lines
- 38 Pest scenarios ready to run
- 4 new CI verification blocks (three fix-specific + one Pest runner)
- TypeScript sanity check on modified `.tsx` files (only pre-existing strict-mode noise from the project's own types)

**You must run to sign off:**

```bash
php artisan optimize:clear
php artisan migrate                                                     # applies 2027_01_01 digest columns
php artisan test --filter=Phase11B43                                    # 38 scenarios
php artisan test --filter=Phase11B42                                    # 43 v11B.4.2 regression
php artisan test --filter=Phase11B4                                     # 56 v11B.5 regression
php artisan vendor-intelligence:generate --send-emails                  # Fix 2 dispatch proof
php artisan schedule:list | grep vendor-intelligence                    # regression (v11B.4.2)
npm ci && npm run typecheck && npm run build
```

## Issue 1 — Admin Vendor Intelligence tab was still not appearing in the UI

### Root cause

v11B.4.2 fixed the route regex (`admin/site-settings/{group}` where clause) and added `validateVendorIntelligence()` to the controller — that closed the write path from `curl`. But the **admin browser UI** still couldn't see or edit the group because **three additional gates** blocked it:

1. `SiteSettingsController::index()` had a hard-coded `$groups` array excluding `vendor_intelligence` → the Inertia payload never included the current values, so the React form had nothing to render
2. `SiteSettingsController::update()` had a **second** allowlist (`$allowed`, separate from the route regex) that also excluded `vendor_intelligence` → any POST from the UI would `abort 422 'Unknown group'`
3. React `resources/js/Pages/Admin/SiteSettings/Index.tsx` had a hard-coded `GROUPS` const and `GroupName` union type both excluding `vendor_intelligence` → no tab rendered even if data was present

Fix required updating all three plus adding UI polish (proper "Vendor Intelligence" label instead of `vendor_intelligence` snake case, and a typed numeric input so int/float values save as numbers not strings).

### Fix — 6 edits

1. `SiteSettingsController::index` — added `'vendor_intelligence'` to `$groups`
2. `SiteSettingsController::update` — added it to `$allowed`
3. `SiteSettingsController::validateVendorIntelligence` — extended to accept `scheduler_enabled`, `stagnant_days`, `digest_emails_enabled`, `digest_min_critical`, `digest_throttle_hours`
4. React `Index.tsx` — extended `GroupName` type + `GROUPS` const
5. React `Index.tsx` — new `GROUP_LABELS` map so `vendor_intelligence` displays as "Vendor Intelligence"
6. React `FieldEditor` — new `typeof value === 'number'` branch with `type="number"` input; sends integer/float values as their native type so Laravel's `integer`/`numeric` validation on the backend receives what it expects (was previously sending everything as string via the fallback branch)
7. `config/site.php` — added `cache_ttl`, `scheduler_enabled`, `digest_emails_enabled`, `digest_min_critical`, `digest_throttle_hours` defaults so those fields appear in the UI form

### Pest evidence (§Fix1.1-11)

- §Fix1.1 Inertia payload has `settings.vendor_intelligence.*`
- §Fix1.2 POST returns 302 (was 422)
- §Fix1.3-6 individual fields update + persist through `SiteSettingsService::get`
- §Fix1.7 negative threshold → validation error
- §Fix1.8 out-of-range value → validation error
- §Fix1.9-10 non-admin blocked (GET + POST)
- §Fix1.11 saved threshold change actually affects `vendor-intelligence:generate` output (stock=8 not flagged at default threshold=5, becomes low after threshold set to 10)

## Issue 2 — Email digest was completely missing

### Root cause

No `app/Mail/`, no `app/Jobs/*Digest*`, no `resources/views/emails/` — the entire mail infrastructure needed to be created. v11B.4 declared "in-dashboard notifications only" and v11B.4.2 explicitly deferred emails. Time to build.

### Design decisions

- **Async by default** — `SendVendorIntelligenceDigest implements ShouldQueue`. The generate command dispatches; the queue worker sends. On environments with `QUEUE_CONNECTION=sync`, dispatch runs immediately (Laravel default fallback).
- **All send-side gates in one place** — the job's `handle()` enforces every skip condition from §4. Callers dispatch blindly; the job decides.
- **Throttle at data layer, not cache** — `last_digest_sent_at` on `vendor_intelligence_summaries` is a real column that survives cache flushes and restarts.
- **PII whitelist not blacklist** — `array_intersect_key($alert->evidence, array_flip([...safe_keys...]))` strips anything unexpected. If a future alert adds `customer_note` to evidence, it's dropped by default rather than needing an explicit rule.
- **Master switch defaults OFF** — `site.vendor_intelligence.digest_emails_enabled = false` in config. Admin has to consciously turn it on via the settings tab. No surprise emails on migration.

### Files added (5)

1. `database/migrations/2027_01_01_000001_add_vendor_intelligence_digest_columns.php` — adds `last_digest_sent_at` (nullable timestamp, indexed) + `email_opted_out` (boolean default false)
2. `app/Mail/VendorIntelligenceDigestMail.php` — Mailable using `markdown('emails.vendor-intelligence-digest')`
3. `app/Jobs/SendVendorIntelligenceDigest.php` — ShouldQueue with 8-gate handle()
4. `resources/views/emails/vendor-intelligence-digest.blade.php` — uses `@component('mail::message')`, `mail::table`, `mail::button` for consistent Laravel mail styling
5. Command signature `--send-emails` in `GenerateVendorIntelligence`; dispatches after each vendor's `regenerateForVendor`

### The 8 send-side gates in `SendVendorIntelligenceDigest::handle()`

Per directive §4 requirements, each maps to a "must not send if" condition:

| # | Gate | Skip when |
|---|---|---|
| 1 | `$manager->isEnabled()` | Master feature flag `vendor_intelligence.enabled` is off |
| 2 | `digest_emails_enabled` | Digest emails opt-in flag is off |
| 3 | `$vendor->status !== STATUS_APPROVED` | Pending/rejected/suspended vendor |
| 4 | `filter_var($to, FILTER_VALIDATE_EMAIL)` | Vendor has no/invalid email address |
| 5 | `email_opted_out` on summary | Per-vendor opt-out |
| 6 | `$activeAlerts->isEmpty()` + `digest_min_critical` | No active alerts / not enough critical |
| 7 | `last_digest_sent_at->diffInHours(now()) < throttleHours` | Within throttle window |
| 8 | Vendor summary doesn't exist | Never generated → nothing to summarize |

### PII discipline

The Blade template renders only aggregated counters + product names / variant labels / search terms. Alert evidence is filtered through an explicit safe-key allowlist in the job before ever reaching the template:

```php
'evidence' => array_intersect_key((array) $a->evidence, array_flip([
    'product_name', 'variant_label', 'stock', 'threshold',
    'recent_orders', 'age_days', 'days_since_last_order',
    'views', 'purchases', 'conversion_rate',
    'wishlist_adds', 'cart_adds', 'abandonment',
    'window_days', 'search_term', 'search_count', 'locale',
])),
```

If a future service leaks `customer_id` or `customer_email` into `evidence`, it's dropped here. §Fix2.12 tests exactly this by planting `customer_email` and `customer_name` in an alert's evidence then asserting the dispatched Mail data has neither.

### Localization

13 en + 13 ar keys added for subject, greeting, table headers, CTA, footer. The job sets `Mail::to()->locale($vendor->user->locale)` so Blade `__()` calls resolve to the vendor's preferred locale.

### Pest evidence (§Fix2.1-13)

- §Fix2.1 approved vendor with critical alert receives email
- §Fix2.2 vendor with no alerts → no email
- §Fix2.3 suspended vendor → no email (even with planted alerts)
- §Fix2.4 pending vendor → no email
- §Fix2.5 opted-out vendor → no email
- §Fix2.6 master `digest_emails_enabled=false` → no email
- §Fix2.7 `vendor_intelligence.enabled=false` → no email
- §Fix2.8 second dispatch within throttle window → no duplicate
- §Fix2.9 `last_digest_sent_at` recorded after successful send
- §Fix2.10 `--send-emails` dispatches N jobs for N approved vendors (verified via `Queue::fake()`)
- §Fix2.11 job implements `ShouldQueue` (reflection assertion)
- §Fix2.12 planted customer PII stripped before template rendering
- §Fix2.13 `digest_min_critical=0` allows non-critical alerts to trigger

## Issue 3 — Product translation edits didn't mark vendor stale

### Root cause

v11B.4.2 added `ProductObserver` that catches changes to `Product::name_translations` (a JSON column) because updating the JSON goes through `Product::update()`. But the project ALSO has a separate normalized `product_translations` table with a translator workflow (`missing → pending → machine_draft → human_reviewed → approved → rejected → stale`) that Filament edits through the `ProductTranslation` model DIRECTLY — no Product::update() involved. Those workflow edits never hit ProductObserver, so vendor intelligence stayed unaware of translation approvals.

### Fix

New `app/Observers/VendorIntelligence/ProductTranslationObserver.php`:

- `created` → mark stale with reason `product_translation_created`
- `updated` → only marks stale when `value`, `status`, `reviewed_by`, or `source_provenance` changed (cosmetic `touch()` calls are ignored) — reason includes field name and new status
- `deleted` → mark stale with reason `product_translation_deleted:{field}`

Null-safety: `$translation->product` can be null (product was hard-deleted); `$product->vendor_id` can be 0/null (orphaned row). Both cases return silently with a warn log. The observer wraps everything in try/catch so a data-integrity issue in a translation row never crashes the mutation.

Registered in `AppServiceProvider::boot()` right after the three v11B.4.2 observers.

### Pest evidence (§Fix3.1-8)

- §Fix3.1 create translation → stale_at set
- §Fix3.2 update `value` → stale_at set
- §Fix3.3 approve translation (status change) → stale_at set + reason contains 'translation'
- §Fix3.4 delete translation → stale_at set
- §Fix3.5 `->touch()` (timestamp-only) → stale_at NOT set
- §Fix3.6 product hard-deleted, then translation update → no crash, silently returns
- §Fix3.7 stale_reason contains "translation" text
- §Fix3.8 `--stale-only` regenerates the translation-marked vendor and clears stale_at

## Files touched

**Modified (8):**
- `VERSION` → `Phase 11B.4 v11B.4.3`
- `app/Http/Controllers/Admin/SiteSettingsController.php` — Fix 1 (3 method changes)
- `app/Console/Commands/GenerateVendorIntelligence.php` — Fix 2 (--send-emails)
- `app/Providers/AppServiceProvider.php` — Fix 3 (translation observer registration)
- `app/Models/VendorIntelligenceSummary.php` — Fix 2 (new column casts)
- `config/site.php` — Fix 1 (new defaults so UI shows the fields)
- `resources/js/Pages/Admin/SiteSettings/Index.tsx` — Fix 1 (GROUPS + GROUP_LABELS + number input)
- `.github/workflows/ci.yml` — +4 v11B.4.3 verification blocks
- `lang/en.json` + `lang/ar.json` — 13 new keys each for digest email content

**New (6):**
- `database/migrations/2027_01_01_000001_add_vendor_intelligence_digest_columns.php` — Fix 2
- `app/Mail/VendorIntelligenceDigestMail.php` — Fix 2
- `app/Jobs/SendVendorIntelligenceDigest.php` — Fix 2
- `resources/views/emails/vendor-intelligence-digest.blade.php` — Fix 2
- `app/Observers/VendorIntelligence/ProductTranslationObserver.php` — Fix 3
- `tests/Feature/Phase11B43FinalVendorIntelligenceRepairTest.php` — 38 scenarios

## Preservation

All v11B.4.2 fixes intact (verified via static grep):
- Routes still in vendor:approved group (`grep 'vendor.intelligence'` inside the `vendor:approved` group opener at line 182+)
- Scheduler entries still in `routes/console.php`
- `Manager::isEnabled()` unchanged, still called from controller/generate/prune
- Migration `2026_12_01` for dedupe + stale still present
- `via_active_dedupe_uniq` UNIQUE index migration intact
- 3 v11B.4.2 observers still registered
- Vendor Report Embed + Product Quality Badge components intact
- 43-scenario Phase11B42 test suite preserved
- v11B.5 fixes intact (ProductQualityService images() + Manager support_tickets)

All prior-phase markers intact (v11B.3.3 CSS + siteSettings; v11B.3.2 vendor Settings; v11B.3 personalization; v11B.2.2 canonical pricing; v10.13 vendor-nav-reports testid).

## Deferrals (honest)

- **No dedicated vendor preferences UI** for the digest opt-out — the `email_opted_out` column exists and works; the DB row is writable, but there's no `/vendor/settings` form field yet. That's a separate UX task. Admins can set it via a direct DB update for testing.
- **No admin observability panel for stale vendors** — `stale_at` and `last_generated_at` are on summaries and exposed in the JSON payload, but there's no dedicated admin table with "vendors marked stale". Could be added to `/admin/vendor-intelligence`.
- **No email preview route** — the Mailable renders via `markdown()` so `php artisan tinker` + `(new VendorIntelligenceDigestMail($vendor, $data))->render()` shows the HTML, but there's no `/admin/mail/vendor-intelligence-digest/preview` URL.

## Rollback

Three tiers documented in PHASE_11B_4_3_ROLLBACK.md:
- Tier 1: rollback the `2027_01_01` migration + revert 2 files (SiteSettingsController + Index.tsx)
- Tier 2: full revert to v11B.4.2 baseline
- Tier 3: chain back to v11B.4 baseline or v11B.3.3 approved

## Phase 11B.4 v11B.4.3 STOPS HERE

Sign-off waits on `php artisan test --filter=Phase11B43` output showing 38/38 pass.
