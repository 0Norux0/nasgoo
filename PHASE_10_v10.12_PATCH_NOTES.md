# Phase 10 v10.12 — Patch Notes

## What's fixed

| Defect | Root cause | Fix |
|---|---|---|
| `/admin/reports` returns `SQLSTATE[42S22] Unknown column 'role' in 'WHERE'`. Surfaces immediately after v10.11's §5 SQL fix made the payout query work — the next downstream query in `marketplaceCounts()` then ran and hit the same class of schema mismatch. | `app/Domain/Reports/ReportsService.php` line 200 queried `DB::table('users')->where('role', 'customer')->count()`. The users table has no `role` column. The project uses Spatie Permission with role names stored in `roles.name` joined via `model_has_roles` — there's no denormalized `role` column on `users` and never has been. | Replace with the Spatie-provided `User::role('customer')->count()` scope, which produces a clean INNER JOIN through `model_has_roles` to `roles`. Adds `use App\Models\User;` import. **All other queries in `marketplaceCounts()` and `ReportsService` are audited and clean** — no other `where('role', ...)` patterns anywhere in `app/`. The vendor counts (`vendors_approved/pending/rejected`) already correctly hit the `vendors` table by its `status` column (which is a real column on a real table). |

## Counts

| | v10.11 → v10.12 |
|---|---|
| Phase 10 CI sub-checks | 50 → 52 |
| Phase 10 Pest scenarios | 155 → 170 |
| PHP source files modified | 1 (`ReportsService.php`) |
| React files modified | 0 |
| New files | 1 (Pest test file) |
| v1-v9 files touched | 0 |
| v10.0-v10.11 fix code reverted | 0 |
| Helpers added | 3 (`p1012_seed_roles_and_permissions`, `p1012_admin`, `p1012_make_user_with_role`) — 71 total unique, 0 duplicates |

## Count semantics (the v10.12 audit per dev §4)

- `customers_total` — users assigned the Spatie **`customer`** role (the v10.12 fix site)
- `vendors_approved` / `vendors_pending` / `vendors_rejected` — rows in the **`vendors`** table by `status` column (vendor APPLICATIONS, not user-role assignments)
- `products_*` / `services_*` / `bookings_*` / `support_tickets_*` / `reviews_*` — query real columns on real tables (unchanged from v10.11; audited as part of v10.12)

A user with multiple Spatie roles (e.g. `customer` + `admin_staff`) is counted once in `customers_total` via the INNER JOIN — Pest scenario verifies.

## Access rules — preserved

v10.10's direct guard in `ReportsController::guardAdminReportsAccess` is untouched. v10.12 modifies only the data layer.

| Role | `/admin/reports` |
|---|---|
| `super_admin` / `admin_staff` / `admin` / `administrator` (any status) | 200 |
| `vendor` | 403 |
| `customer` | 403 |
| Guest | redirect /login |

## Per dev §15 acceptance

**Phase 10 v10.12 is implemented but requires developer runtime verification.**

Dev runs:
```bash
php artisan optimize:clear
php artisan migrate
php artisan test --filter=Phase10V1012
php artisan permission:cache-reset
npm run typecheck && npm run build
```

Then restarts the Laravel server from the active folder, logs out, logs back in as the same administrator, and walks confirmations in `PHASE_10_v10.12_DEVELOPER_CHECKLIST.md`.

## Why this and v10.11 §5 are related but distinct

v10.11 §5 fixed `SUM(amount_minor)` → `SUM(requested_amount_minor)` on `vendor_payout_requests`. That query ran first in `ReportsController::index`. Once it succeeded, the controller then called `marketplaceCounts()` which immediately hit the next schema mismatch — `users.role`. This is normal cascade behavior: each layer of a complex page can hide the next layer's defect until you fix it. v10.12 fixes this next layer. After v10.12 there are no remaining `where('role', ...)` queries against `users` anywhere in `app/` (CI-enforced).
