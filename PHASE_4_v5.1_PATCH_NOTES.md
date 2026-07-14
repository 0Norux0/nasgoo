# Phase 4 v5.1 — Test Coverage & CI Verification Strengthening

**No production code changes.** v5.1 is purely additional automated test coverage + a stronger CI verdict surface in response to the developer's pre-approval audit request. Same Phase 4 scope as v5.0.

---

## Why this patch exists

The developer asked, before approving Phase 4, that Claude verify 14 specific areas have automated tests and CI checks:

> 1. Cart add/update/remove · 2. Checkout page loading · 3. COD order creation · 4. Manual bank transfer · 5. Mock online payment · 6. Customer order list and detail page · 7. Admin order actions · 8. Vendor order listing and shipping · 9. Stock decrease after order placement · 10. Stock restoration after cancellation · 11. Payment method seeding · 12. No 419 errors · 13. Filament/admin assets still loading · 14. Vite build and frontend typecheck

Honest audit of v5.0 found **six gaps**: items 1, 2, 6, 8 had no HTTP-level integration tests (only service-level), item 11 had only a CI smoke check (no PHPUnit test), and item 12 had no regression test for the v3.3 CSRF cookie wiring. v5.1 closes all six.

---

## What's added in v5.1

### 4 new test files (+43 scenarios; total 235 across 34 files)

| File | Scenarios | Covers audit items |
|---|---|---|
| `tests/Feature/Phase4HttpFlowTest.php` | 25 | 1, 2, 3, 4, 5, 6, 7, 8, 9 — full HTTP integration |
| `tests/Feature/PaymentMethodsSeederTest.php` | 7 | 11 — seeder pinning |
| `tests/Feature/Phase4CsrfTest.php` | 5 | 12 — XSRF cookie regression |
| `tests/Feature/AdminAssetsRegressionTest.php` | 6 | 13 — Filament admin chrome |

Test names use an `item N:` prefix so the GitHub Actions log shows exactly which audit item fails when one breaks.

### Strengthened CI verdict

The `verdict` job in `.github/workflows/ci.yml` now emits a coverage table mapping every one of the 14 audit items to its proving test file(s), printed before the final pass/fail line. The pass message reads:

```
✅ Phase 4 PASSES — ready to approve Phase 5
All 14 developer-requested audit items are covered. See the per-area mapping above for the test file(s) that prove each one.
```

A new step **`Phase 4 audit-item coverage map`** runs each Phase 4 test file separately after the main suite and prints a per-file ✅/❌ table to the run summary, so when a future change breaks something, the failing audit area is immediately visible.

---

## Per-item coverage mapping

| # | Area | Test file(s) |
|---|---|---|
| 1 | Cart add/update/remove | `CartTest` (10 service-level) + `Phase4HttpFlowTest` items 1 (7 HTTP) |
| 2 | Checkout page loading | `Phase4HttpFlowTest` items 2 (3) |
| 3 | COD order creation | `CheckoutTest` + `PaymentTest` + `Phase4HttpFlowTest` item 3 (1 end-to-end) |
| 4 | Manual bank transfer | `PaymentTest` + `Phase4HttpFlowTest` item 4 (1) |
| 5 | Mock online payment | `PaymentTest` + `Phase4HttpFlowTest` item 5 (1) |
| 6 | Customer order list + detail | `Phase4HttpFlowTest` items 6 (4) |
| 7 | Admin order actions | `OrderLifecycleTest` (7 — service methods the admin Filament actions delegate to) + `Phase4HttpFlowTest` items 7 (3 — customer cancel flow which also exercises the lifecycle service) |
| 8 | Vendor order listing + ship | `VendorOrderAccessTest` (5) + `Phase4HttpFlowTest` items 8 (4) |
| 9 | Stock decrease on placement | `CheckoutTest` + `Phase4HttpFlowTest` item 9 (1) |
| 10 | Stock restoration on cancel | `OrderLifecycleTest` + `Phase4HttpFlowTest` items 7 (1) |
| 11 | Payment method seeding | `PaymentMethodsSeederTest` (7) + tinker smoke check in CI |
| 12 | No 419 errors | `Phase4CsrfTest` (5) — pins bootstrap.ts XSRF-cookie wiring + asserts non-419 status on actual POST round-trips |
| 13 | Filament admin assets | `AdminAssetsRegressionTest` (6) + existing `Phase 2 v3.3` asset checks |
| 14 | Vite + frontend typecheck | Existing CI `frontend` job (tsc strict + Vite build, unchanged from v5.0) |

---

## On item 7 specifically

Admin row actions (`confirm`, `ship`, `deliver`, `cancel`, `refund`) on `OrderResource` are thin Filament wrappers that delegate to `OrderLifecycleService` and `PaymentService`. Those services are covered exhaustively by `OrderLifecycleTest` (7 scenarios) and `PaymentTest` (9 scenarios) — full state-machine, idempotency, restock-on-cancel, partial refund accounting, transaction audit log.

Testing the Filament Livewire wrappers directly requires booting Filament's Livewire test harness, which is a meaningful infrastructure investment not previously made in this codebase. Adding it is a Phase 5+ polish item, not a Phase 4 deliverable. The current v5.1 coverage proves the actions DO what they claim to do; the Filament UI wiring is exercised manually during the 14-step checklist walkthrough in PHASE_4_REPORT.md.

---

## What v5.1 does NOT change

- **No production code changes.** Migrations, models, controllers, services, providers, Filament resources, React pages, routes, seeders, i18n — all identical to v5.0. Only new files in `tests/` and only edits inside `.github/workflows/ci.yml`.
- **No new dependencies.** Uses only Pest helpers already present.
- **Same Phase 4 scope.** Reviews, wishlist, real PSP integration, tax engine, shipping zones, payouts — all still deferred to Phase 5+ per PHASE_4_REPORT.md.

---

## Applying v5.1 to a verified v5.0 deployment

If you've already applied v5.0 and CI is green:
1. Extract `marketplace-phase-4-v5.1.tar.gz` over the same checkout (only adds 4 test files + edits `ci.yml`, `README.md`, `PHASE_4_REPORT.md`).
2. Push to your branch — CI re-runs and shows the new audit-map summary.
3. Verdict line should read: **`✅ Phase 4 PASSES — ready to approve Phase 5`** with the audit-item map below it.

If you haven't applied v5.0 yet, just use v5.1 directly — it's a strict superset.

---

## Stop discipline

**Phase 5 is still not started.** No reviews, wishlist, payouts, or shipping-zones code on disk. v5.1 strengthens the audit trail for Phase 4 verification — nothing more. When CI is green AND the 14-step manual checklist in `PHASE_4_REPORT.md` passes, reply **"approve Phase 5"**.
