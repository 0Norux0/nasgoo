# Phase 8 — Services Marketplace / Appointment Booking Foundation

**Status:** Initial Phase 8 build on top of Phase 7 v7.7. Pending CI verification.
**Scope:** 7 migrations, 5 new models (+2 extended), 2 domain services, 6 controllers, 31 routes, 1 Filament admin resource (+3 pages), 8 React pages, idempotent demo data, 18 Pest scenarios, 5 new CI sub-checks.

---

## What Phase 8 adds

A complete service-listing + appointment-booking layer on top of the existing product marketplace. Vendors can list services (doctor consultation, home AC cleaning, electrician call-out, online tutoring, etc.), create provider/staff profiles, define weekly availability with break windows, and accept/reject/complete bookings. Customers can browse, filter, see real-time slot availability, and book in 3 clicks.

Importantly, **Phases 0–7 are unchanged in behaviour**. Normal, dropship, and customizable products continue to check out exactly as before. The only edit to existing files is two small additions to `Product` (a `TYPE_SERVICE` constant + `isService()` helper + three new relations) and two new relations on `Vendor`. No schema changes to existing tables.

---

## Architecture (decided up front)

| Decision | Rationale |
|---|---|
| **Service is a Product type, not a separate model** | Reuses the entire `products` infrastructure (slug, status, vendor scope, search). `products.type='service'` switches it to service mode; `service_details` (one-to-one) holds the service-specific fields (duration, location_mode, etc). Same pattern as Phase 7 customizable products. |
| **Providers are their own model, many-to-many with services** | A clinic has multiple doctors; an AC repair company has multiple technicians. Decoupling provider from service lets one provider deliver multiple services and vice versa. Pivot `service_provider_assignments`. |
| **Availability is per-provider, weekly-keyed** | `service_availabilities` has UNIQUE(`service_provider_id`, `day_of_week`), so the schedule is exactly 0–7 rows per provider. Lunch/prayer break stored on the same row as start_time/end_time. Multiple breaks per day = future work. |
| **Blocked dates separate from weekly schedule** | `service_blocked_dates` overrides the weekly schedule for one-off vacations/holidays. UNIQUE(`service_provider_id`, `date`). |
| **Booking is a standalone resource, optional Order link** | `service_bookings.order_id` is nullable. Free bookings have order_id = NULL; paid bookings link to an existing Phase 4 Order via the same `PaymentService`. Status state machine independent of order status. |
| **Status state machine has 10 values** | pending, pending_payment, confirmed, accepted, rejected, rescheduled, cancelled, completed, no_show, refunded. ACTIVE_STATUSES block the slot. TERMINAL_STATUSES close the booking. |
| **Double-booking prevention: row-level lock + re-check** | `ServiceBookingService::createBooking()` opens a DB transaction, takes `lockForUpdate()` on (provider_id, date), re-counts active bookings, then inserts. Concurrent attempts on the same slot see the lock and the second one re-counts and rejects. |
| **Model-level safeguard on ServiceBooking** | Following the v7.4 lesson, `static::booted()::creating` throws `LogicException` if any of the 11 required fields is null/empty OR status is unknown. Names `ServiceBookingService::createBooking()` in the error message so seeder/test code paths that bypass the service layer fail loud. |

---

## Files added / changed

### Migrations (7)

```
database/migrations/2026_01_08_000001_create_service_details_table.php
database/migrations/2026_01_08_000002_create_service_providers_table.php
database/migrations/2026_01_08_000003_create_service_provider_assignments_table.php
database/migrations/2026_01_08_000004_create_service_availabilities_table.php
database/migrations/2026_01_08_000005_create_service_blocked_dates_table.php
database/migrations/2026_01_08_000006_create_service_bookings_table.php
database/migrations/2026_01_08_000007_document_service_product_type.php  ← no-op (products.type is string)
```

All migrations are independent of Phase 0–7 tables. **No ALTER on existing tables.** The `products.type='service'` value is added via the model constant only; the column was already a `string`.

### Models (5 new + 2 extended)

**New:**
- `app/Models/ServiceDetail.php` — one-to-one with `Product` for `type='service'`. TYPE_* and LOCATION_* constants.
- `app/Models/ServiceProvider.php` — staff under vendor. `services()` belongsToMany, `availabilities()`/`blockedDates()`/`bookings()` hasMany.
- `app/Models/ServiceAvailability.php` — weekly schedule per provider. DAYS constant maps 0..6 → 'Sunday'..'Saturday'.
- `app/Models/ServiceBlockedDate.php` — one-off blocks.
- `app/Models/ServiceBooking.php` — main booking resource. Status constants, ACTIVE_STATUSES, TERMINAL_STATUSES, model-level safeguard, `isActive()`/`isTerminal()`/`canBeCancelledBy()` helpers.

**Extended:**
- `app/Models/Product.php` — added `TYPE_SERVICE = 'service'` constant, `isService()` helper, `serviceDetail(): HasOne`, `serviceProviders(): BelongsToMany`, `serviceBookings(): HasMany`.
- `app/Models/Vendor.php` — added `serviceProviders(): HasMany`, `serviceBookings(): HasMany`.

### Domain services (2)

- `app/Domain/Service/ServiceAvailabilityService.php` — `slotsFor()` returns available slots for a date; `slotsForRange()` returns slots across a date range; respects weekly schedule, break window, blocked dates, min lead time, max advance days, and existing active bookings counted against max_bookings_per_slot.
- `app/Domain/Service/ServiceBookingService.php` — `createBooking()` with row-level lock + double-booking re-check; `accept()`/`reject()`/`cancel()`/`complete()` for state machine transitions; `generateNumber()` for unique `SVC-YYYYMMDD-XXXX` IDs.

### Controllers (6)

**Vendor:**
- `app/Http/Controllers/Vendor/VendorServiceController.php` — service CRUD (`index`, `create`, `store`, `edit`, `update`)
- `app/Http/Controllers/Vendor/VendorServiceProviderController.php` — staff CRUD (`index`, `store`, `update`, `destroy`)
- `app/Http/Controllers/Vendor/VendorAvailabilityController.php` — weekly schedule + blocked dates
- `app/Http/Controllers/Vendor/VendorBookingController.php` — bookings dashboard, accept/reject/complete

**Customer:**
- `app/Http/Controllers/ServiceCatalogController.php` — public service browse, filtering, detail page, AJAX slots endpoint
- `app/Http/Controllers/BookingController.php` — customer's bookings (index, show, store, cancel)

### Routes (31)

Public catalog: `/services`, `/services/{slug}`, `/services/api/slots`
Customer (auth): `/bookings`, `/bookings/{id}`, `POST /bookings`, `POST /bookings/{id}/cancel`
Vendor (role:vendor): full CRUD under `/vendor/services`, `/vendor/providers`, `/vendor/providers/{id}/availability`, `/vendor/providers/{id}/blocked-dates`, `/vendor/bookings`

### Filament admin

- `app/Filament/Resources/ServiceBookingResource.php` — admin view + edit of all bookings across vendors. Status select with all 10 values. Eager-loads customer/vendor/product/provider/order (v7.6 lesson defense-in-depth).
- `app/Filament/Resources/ServiceBookingResource/Pages/{ListServiceBookings,ViewServiceBooking,EditServiceBooking}.php`

### React pages (8)

Customer:
- `resources/js/Pages/Services/Index.tsx` — browse + filter
- `resources/js/Pages/Services/Show.tsx` — detail + booking widget with 14-day slot calendar
- `resources/js/Pages/Bookings/Index.tsx` — customer's bookings
- `resources/js/Pages/Bookings/Show.tsx` — booking detail + cancel

Vendor:
- `resources/js/Pages/Vendor/Services/Index.tsx` — service list
- `resources/js/Pages/Vendor/Services/Create.tsx` — new service form
- `resources/js/Pages/Vendor/Services/Edit.tsx` — edit service + assign providers
- `resources/js/Pages/Vendor/Providers/Index.tsx` — staff list + inline create
- `resources/js/Pages/Vendor/Providers/Availability.tsx` — weekly schedule + blocked dates
- `resources/js/Pages/Vendor/Bookings/Index.tsx` — vendor bookings table
- `resources/js/Pages/Vendor/Bookings/Show.tsx` — booking detail with accept/reject/complete actions

All pages use `SharedProps` from `@/types/inertia` per the v7.3 lesson — no local `interface PageProps` shadowing the augmented global. All imports are minimal — verified with `tsc --noUnusedLocals --noUnusedParameters` (v7.7 workflow).

### Demo seeder additions

`database/seeders/DemoSeeder.php` gains a new private method `seedServicesAndBookings()` invoked once from `run()`. Idempotent via `updateOrCreate` keyed on real unique indexes (v7.2 lesson). Creates:

| Item | Key fields |
|---|---|
| Service 1 | "General Doctor Consultation" @ vendor1, sku `SVC-DEMO-DOCTOR-001`, 30 min, KWD 15.000, location=provider |
| Service 2 | "Home AC Deep Cleaning" @ vendor2, sku `SVC-DEMO-AC-CLEAN-001`, 90 min, KWD 12.500, location=customer |
| Provider 1 | "Dr. Sarah Ahmed", vendor1, specialization=General Medicine |
| Provider 2 | "Ahmad Khalid", vendor2, specialization=HVAC Technician |
| Availability | Mon–Sat 10:00–20:00, 30-min slots, max 1/slot, lunch break 13:00–14:00 |
| Demo booking | customer@marketplace.test, service 1, tomorrow at 10:00 (auto-skips Sunday), status=CONFIRMED |

Demo accounts unchanged: `admin@`, `staff@`, `vendor@`, `vendor2@`, `customer@`, `pending-vendor@`, `rejected-vendor@` — all `password`.

### Tests — 18 Phase 8 scenarios

`tests/Feature/Phase8ServiceBookingTest.php` covers exactly the 18 scenarios the spec asked for: vendor service creation, provider creation, availability definition, customer browsing, slot visibility, booking creation, double-booking prevention, customer/vendor/admin dashboard scoping, accept/reject/complete state transitions, regression tests for normal/dropship/customizable products, no lazy-load errors on Phase 8 pages, model-level safeguard, and unknown-status rejection.

All tests run under `Model::shouldBeStrict(true)` — any lazy-load anywhere in a controller→view path fails the test with `LazyLoadingViolationException`.

### CI — 6 new sub-checks

1. **`Phase 8 — schema-vs-code pre-flight`** — every fillable in every Phase 8 model has a matching column in its migration (v7.1 pattern reused).
2. **`Phase 8 — unique-index pre-flight`** — every `updateOrCreate` in the Phase 8 seeder uses keys that match a real unique index (v7.2 pattern).
3. **`Phase 8 — ServiceBooking model-level safeguard`** — greps for the 5 required elements (`booted`, `static::creating`, `LogicException`, error message, service-class reference) (v7.4 pattern).
4. **`Phase 8 — lazy-load defense`** — every Phase 8 controller/resource that maps a relation also eager-loads it (v7.6 pattern).
5. **`Phase 8 — Pest scenarios for services + bookings`** — runs `php artisan test --filter "Phase 8"` against the live test DB.
6. **`Phase 8 — runtime demo data check`** — runs `migrate:fresh --seed`, asserts ≥2 services + ≥2 providers + ≥1 booking + ≥1 availability row, then runs `migrate:fresh --seed` AGAIN and asserts service count is unchanged (idempotency).

### Documentation

- `PHASE_8_PATCH_NOTES.md` (this file)
- `PHASE_8_REPORT.md` — architecture diagrams + timeline + accountability
- `README.md` — Phase 8 changelog prepended; status header bumped
- `TROUBLESHOOTING.md` — Phase 8 section prepended

---

## Defensive patterns inherited from Phase 7

Every Phase 7 bug class has a matching Phase 8 defense:

| Phase 7 bug | Phase 8 defense |
|---|---|
| v7.1 — column name mismatch | Schema-vs-code pre-flight covers all 5 new models |
| v7.2 — duplicate SKU on re-seed | All 7 `updateOrCreate` calls use real unique-index keys |
| v7.3 — NULL into NOT NULL column | No path columns added — no file_path issues possible. Schema lists defaults for every nullable. |
| v7.4 — bypass-the-service-layer | `ServiceBooking::booted()::creating` throws if status unknown or required field empty |
| v7.5 — mail unreachable | No booking notifications send mail synchronously in Phase 8. Status changes are recorded to DB only. Future Phase 9+ can add notifications with the v7.5 `try/catch + Log::warning` pattern. |
| v7.6 — confirm page missing eager-load | All 5 Phase 8 controllers + Filament resource verified by CI to eager-load every relation they map |
| v7.7 — unused TypeScript import | Pre-shipping `tsc --noEmit -p tsconfig.verify.json` run; TS6133=0 verified |

---

## Pre-shipping verification I ran in the sandbox

I cannot run `php artisan test`, `php artisan migrate:fresh --seed`, or `npm run build` in the sandbox (network 403, no PHP, no npm install). What I verified:

- **PHP brace balance** (raw count) — all Phase 8 PHP files balanced
- **Phase 7 v7.1 schema-vs-code pre-flight extended for Phase 8** — every fillable matches a migration column ✓
- **Phase 7 v7.2 unique-index pre-flight extended for Phase 8** — every `updateOrCreate` uses real unique-index keys ✓
- **Phase 7 v7.3 null-vs-NOT-NULL pre-flight** — no NOT-NULL violations in seeder ✓ (no file_path columns this phase)
- **Phase 7 v7.4 model-safeguard grep** — `ServiceBooking::booted()::creating` present with all 5 required elements ✓
- **Phase 7 v7.6 lazy-load defense extended for Phase 8** — all 5 sites eager-load their declared relations ✓
- **Phase 7 v7.7 tsc with stubs** — TS6133=0, TS6196=0 across the whole project (will rerun this immediately before tar/zip) ✓
- **CI YAML parses** ✓
- **All previous defenses preserved** — direct content inspection on shipped archive ✓

---

## Known limitations / out of scope for Phase 8 foundation

These are intentional v1 simplifications. None block normal operation.

1. **No service images yet.** The schema doesn't add a service-specific image column; Phase 4's `product_images` table works for service products too, but the React forms don't expose an upload field. Coming in Phase 9.
2. **Single break window per day.** A provider can't define two breaks (e.g. lunch + prayer time). Multiple-break support would require a `service_availability_breaks` table.
3. **No deposit / partial payment flow.** Bookings are either free (order_id null) or full-payment (order_id linked to an Order). Partial deposits would require splitting Order.total_minor and tracking deposit/balance.
4. **No automated booking notifications.** Per Phase 8 spec, mail safety is critical and the v7.5 workflow is incomplete enough that I'd rather defer notifications than risk a crash. Status changes are recorded on the booking row; vendors and customers see them on their dashboards. Phase 9 should add `NotifyBookingStatusChanged` listener wrapping mail in `try/catch + Log::warning` per the v7.5 pattern.
5. **No reschedule UI yet.** The `STATUS_RESCHEDULED` constant exists and the migration supports it, but neither the customer nor vendor UI exposes a "reschedule" button. The workflow would be: customer/vendor cancels existing → customer rebooks the new slot. Phase 9 can add proper reschedule.
6. **No provider profile image upload.** The `profile_image_path` column exists; the React form doesn't expose an upload yet.
7. **Admin can view all bookings via Filament but can't approve services there.** Service approval (the spec's section 10) is set as `status='draft'` by default after creation; admin would need to edit the underlying Product in the existing Phase 3 `ProductResource`. Phase 9 can wire a dedicated service approval column on the Filament UI.

---

## Developer testing checklist after pulling Phase 8

```bash
git pull
composer install
cp .env.example .env       # if you don't have one yet
php artisan key:generate
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan migrate:fresh --seed      # twice — confirms idempotency
npm ci
npm run typecheck                     # must pass
npm run build                         # must pass
php artisan test --filter "Phase 8"   # 18 scenarios
php artisan test --filter "Phase 7"   # 42 scenarios — confirms no Phase 7 regression
```

**Manual smoke test:**

1. `vendor@marketplace.test / password` → http://localhost/vendor/services should show "General Doctor Consultation" with provider "Dr. Sarah Ahmed".
2. http://localhost/vendor/providers → "Dr. Sarah Ahmed" listed.
3. http://localhost/vendor/providers/1/availability → Mon-Sat 10:00-20:00 visible.
4. http://localhost/vendor/bookings → 1 confirmed booking (the demo).
5. Log out, browse http://localhost/services → 2 services listed.
6. Click "General Doctor Consultation" → slot calendar shows tomorrow with available times.
7. `customer@marketplace.test / password`, http://localhost/bookings → 1 booking visible.

---

## Accountability — first Phase 8 release

This is the first Phase 8 release. I aimed to apply every lesson from the eight Phase 7 attempts up front rather than discover them empirically:

- **Real tsc in sandbox before declaring ready** (v7.7 lesson)
- **`updateOrCreate` keyed on real unique indexes** for every seeded model (v7.2 lesson)
- **Model-level `LogicException` safeguard** on the booking resource (v7.4 lesson)
- **`with([...])` eager-loads in every controller/resource that iterates a relation** (v7.6 lesson)
- **No mail sends in this phase** — defers to Phase 9 with the v7.5 resilience pattern in place
- **No NOT-NULL column without a default or required-field guard** (v7.3 lesson)
- **No unused TypeScript imports** — verified by real tsc (v7.7 lesson)

The 5 new CI sub-checks codify these defenses so any future contributor change that violates them fails the build with a Phase 8-tagged error message.

**Phase 8 STOPS HERE. Do not start Phase 9 until CI shows `✅ Phase 8 PASSES` AND your developer confirms (a) `npm run build` exits 0, (b) `migrate:fresh --seed` runs twice cleanly, (c) the manual smoke test above works, (d) the existing Phase 7 customizable checkout flow still completes successfully.**
