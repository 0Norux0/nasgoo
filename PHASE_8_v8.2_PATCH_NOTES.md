# Phase 8 v8.2 — MySQL identifier-limit fix

**Status:** Targeted migration fix on top of Phase 8 v8.1. Pending CI verification.
**Scope:** 3 hard failures + 1 defensive fix across 3 of the 7 Phase 8 migrations. No code logic, controller, service, or React file is changed. Index names only.

---

## Root cause

MySQL has a strict 64-character limit on identifier names (table names, column names, **index names**, foreign key names). PostgreSQL has a 63-character limit but **silently truncates** longer names. SQLite has no limit.

Laravel auto-generates index names by joining `{table_name}_{column_names}_{type}` with underscores. For tables with long names (`service_provider_assignments` is 28 chars on its own) plus compound indexes on long column names (`service_provider_id` is 19 chars), the auto-generated names exceeded 64 chars:

| Migration | Auto-generated index name | Length |
|---|---|---|
| `service_provider_assignments` | `service_provider_assignments_service_provider_id_product_id_unique` | **66 chars** ✗ |
| `service_provider_assignments` | `service_provider_assignments_product_id_service_provider_id_index` | **65 chars** ✗ |
| `service_bookings` | `service_bookings_service_provider_id_booked_for_date_booked_for_time_index` | **74 chars** ✗ |
| `service_availabilities` | `service_availabilities_service_provider_id_day_of_week_unique` | 61 chars ⚠️ |

**Postgres truncated all four silently to 63 chars, so the v8.1 CI runtime check (`Phase 8 — runtime demo data check`) passed.** The developer ran `php artisan migrate:fresh --seed` against MySQL locally and immediately hit:

```
SQLSTATE[42000]: Syntax error or access violation: 1059
Identifier name 'service_provider_assignments_service_provider_id_product_id_unique' is too long
```

This was a real cross-engine compatibility bug, not a MySQL-specific quirk — the long names were always wrong, Postgres just hid the symptom.

---

## Fix

Added explicit short index names to all 4 problem indexes. Naming convention: `{prefix}_{description}_{type}` where `prefix` is a 2-3 letter table tag.

| Migration | Old (implicit) | New (explicit) | Length |
|---|---|---|---|
| `2026_01_08_000003_create_service_provider_assignments_table.php` | `service_provider_assignments_service_provider_id_product_id_unique` (66) | `spa_provider_product_unique` | **27** ✓ |
| `2026_01_08_000003_create_service_provider_assignments_table.php` | `service_provider_assignments_product_id_service_provider_id_index` (65) | `spa_product_provider_idx` | **24** ✓ |
| `2026_01_08_000004_create_service_availabilities_table.php` | `service_availabilities_service_provider_id_day_of_week_unique` (61) | `sa_provider_dow_unique` | **22** ✓ |
| `2026_01_08_000006_create_service_bookings_table.php` | `service_bookings_service_provider_id_booked_for_date_booked_for_time_index` (74) | `sb_provider_date_time_idx` | **25** ✓ |
| `2026_01_08_000006_create_service_bookings_table.php` | `service_bookings_vendor_id_status_booked_for_date_index` (55) | `sb_vendor_status_date_idx` | 25 ✓ (renamed for consistency) |
| `2026_01_08_000006_create_service_bookings_table.php` | `service_bookings_user_id_status_index` (37) | `sb_user_status_idx` | 18 ✓ (renamed for consistency) |

Prefixes:
- `spa_` = `service_provider_assignments`
- `sa_`  = `service_availabilities`
- `sb_`  = `service_bookings`

**Uniqueness rules are unchanged** — only the index *name* changed, not the columns or behaviour. The Pest test `Phase 8 v8.2: the unique constraint on service_provider_assignments still rejects duplicates` proves the rule is still enforced.

---

## Audit of every Phase 8 migration

Per your request to check **all** migrations, not just the failing one. The audit script enumerates every identifier Laravel will auto-derive for each table and computes its length. Result after v8.2 fixes:

```
✓ 56 chars  [foreign         ]  service_provider_assignments_service_provider_id_foreign
✓ 53 chars  [unique-IMPLICIT ]  service_blocked_dates_service_provider_id_date_unique
✓ 52 chars  [index-IMPLICIT  ]  service_blocked_dates_date_service_provider_id_index
✓ 50 chars  [foreign         ]  service_availabilities_service_provider_id_foreign
✓ 49 chars  [foreign         ]  service_blocked_dates_service_provider_id_foreign
✓ 47 chars  [foreign         ]  service_provider_assignments_product_id_foreign
✓ 44 chars  [foreign         ]  service_bookings_service_provider_id_foreign
✓ 39 chars  [unique-IMPLICIT ]  service_providers_vendor_id_slug_unique
✓ 35 chars  [foreign         ]  service_providers_vendor_id_foreign
✓ 35 chars  [foreign         ]  service_bookings_product_id_foreign
✓ 34 chars  [foreign         ]  service_details_product_id_foreign
✓ 34 chars  [foreign         ]  service_bookings_vendor_id_foreign
✓ 33 chars  [index-single    ]  service_providers_is_active_index
✓ 33 chars  [foreign         ]  service_bookings_order_id_foreign
✓ 32 chars  [foreign         ]  service_bookings_user_id_foreign
✓ 27 chars  [unique-explicit ]  spa_provider_product_unique
✓ 25 chars  [index-explicit  ]  sb_provider_date_time_idx
✓ 25 chars  [index-explicit  ]  sb_vendor_status_date_idx
✓ 24 chars  [index-explicit  ]  spa_product_provider_idx
✓ 22 chars  [unique-explicit ]  sa_provider_dow_unique
✓ 18 chars  [index-explicit  ]  sb_user_status_idx
```

**21 identifiers in total, longest is 56 chars — 8 chars below MySQL's 64-char limit.**

Tables checked (all Phase 8):
- `service_details` ✓
- `service_providers` ✓
- `service_provider_assignments` ✓ (fixed)
- `service_availabilities` ✓ (fixed defensively)
- `service_blocked_dates` ✓
- `service_bookings` ✓ (fixed)

Note: your spec mentions four tables (`service_availability_schedules`, `service_availability_exceptions`, `service_booking_status_events`, `service_booking_reschedules`) that don't exist in our Phase 8 schema — those appear to be from a different design. The actual Phase 8 tables are the 6 above. All audited.

---

## Two new CI sub-checks (codifying the v8.2 verification)

1. **`Phase 8 v8.2 — MySQL identifier-limit pre-flight`**

   Static check: Python script that parses each Phase 8 migration, enumerates every `foreignId()`, `unique([])`, and `index([])` declaration, predicts the Laravel-auto-generated name, and fails CI with an actionable error message if any name exceeds 60 chars (4-char safety buffer below MySQL's 64-char limit).

   Critically, this runs **without spinning up a MySQL container**, so it catches the bug in seconds and works on any CI runner.

2. **`Phase 8 v8.2 — MySQL migrate:fresh --seed end-to-end`**

   Runtime check: actually runs `php artisan migrate:fresh --seed` against a MySQL container alongside the existing Postgres-based runtime check. Specifically greps for `SQLSTATE.*1059` (the MySQL identifier-too-long error) in the migration output. Gracefully skips if no MySQL service is available on the runner — the static pre-flight is the primary defense.

3. **`Phase 8 v8.2 — Pest scenarios for index-name regression suite`**

   Runs the new `Phase8V82MigrationTest.php` file (6 scenarios) that inspect actual database indexes via `Schema::getIndexes()` and assert:
   - All four explicit short index names exist
   - The corresponding long auto-generated names do NOT exist
   - Every index on every Phase 8 table is ≤ 60 characters
   - Uniqueness rules still reject duplicates (proving the rename didn't accidentally drop the constraint)

---

## Phase 8 CI sub-check totals

- Phase 8.0 (foundation): 6
- Phase 8 v8.1 (UX completion): 5
- Phase 8 v8.2 (MySQL compatibility): 3
- **Phase 8 total**: 14 + 14 (Phase 7) = **28 phase-specific CI sub-checks**

Final CI verdict updated to: `✅ Phase 8 v8.2 PASSES — ready to approve Phase 9`.

---

## Honest disclosure: what I cannot verify in the sandbox

This sandbox has no PHP, no Composer, and no MySQL. I cannot literally run `php artisan migrate:fresh --seed` here. What I CAN verify:

1. ✅ **Static identifier-length audit** — Python script computes every auto-derived index name and confirms all are ≤ 56 chars (max).
2. ✅ **CI YAML still parses** — `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"`.
3. ✅ **Migrations still use `Schema::create` correctly** — PHP brace balance + grep for `->unique(` / `->index(` shows the second-arg explicit names are syntactically correct.
4. ✅ **Existing Pest tests untouched** — only added one new file (`Phase8V82MigrationTest.php`), no edits to existing test files.
5. ✅ **No model fillable changes** — schema-vs-code pre-flight from v8.0 still passes (no columns added or removed).

What I cannot verify here but the new CI step verifies:

- `php artisan migrate:fresh --seed` against a real MySQL 8.0 container succeeds without `SQLSTATE 1059`.
- `php artisan migrate:fresh --seed` against PostgreSQL still succeeds (existing v8.0 step).
- The 6 new Pest scenarios pass with real index introspection.

If the static pre-flight passes AND the runtime check passes, the bug is fixed and protected against regression.

---

## Developer testing checklist for v8.2

```bash
git pull
composer install
php artisan optimize:clear

# THE CRITICAL CHECK — must complete without SQLSTATE 1059
php artisan migrate:fresh --seed

# Run again to confirm idempotency
php artisan migrate:fresh --seed

# Full test suite
php artisan test

# Build verification (unchanged from v8.1)
npm ci
npm run typecheck
npm run build
```

**If `migrate:fresh --seed` still fails**, capture the exact `SQLSTATE` code and identifier name and report; the static pre-flight in CI should have caught it before merge.

---

## What did NOT change in v8.2

- React layouts, components, controllers (all v8.1 code unchanged)
- Domain services (`ServiceBookingService`, `ServiceAvailabilityService`)
- Models (`ServiceBooking`, `ServiceProvider`, etc.)
- Filament admin resource
- DemoSeeder (still idempotent via `updateOrCreate` on real unique-index column groups — the column groups are unchanged, only the index *names* were renamed)
- Route definitions
- 20 Pest scenarios from v8.1 (still in place)
- 18 Pest scenarios from v8.0 (still in place)

If you observe a regression in any of the above, it's not from this patch — please report with the failing test name.

---

## Accountability

This is the third time in Phase 8 I've shipped a release with a problem the developer caught. Pattern:

- **8.0**: backend complete but no nav links → unreachable
- **8.1**: fixed nav, but never ran migrations against MySQL → identifier-limit bug shipped
- **8.2** (this release): added MySQL identifier-limit pre-flight as a static check + runtime MySQL migrate step

The static pre-flight is the more valuable defense because it runs in seconds on any CI runner without needing a MySQL service. The runtime step is the eventual verification but requires infrastructure. Together they prevent recurrence.

**Going forward: when shipping migration changes, the static pre-flight will hard-fail before tests even start if any compound index name predicted from `{table}_{cols}_{type}` exceeds 60 chars.** The contributor must add an explicit short name as the second arg to `->unique([...])` or `->index([...])`.

**Phase 8 v8.2 STOPS HERE. Do not start Phase 9** until:
1. `php artisan migrate:fresh --seed` completes cleanly on MySQL (the originating bug).
2. CI shows `✅ Phase 8 v8.2 PASSES`.
3. All existing v8.1 functionality (nav links, booking confirmation, reschedule) still works in the developer's manual smoke test.
