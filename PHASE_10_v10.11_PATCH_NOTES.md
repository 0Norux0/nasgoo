# Phase 10 v10.11 — Patch Notes

## What's fixed (4 confirmed runtime defects)

| § | Defect | Root cause | Fix |
|---|---|---|---|
| §5 | `/admin/reports` returns SQL `Unknown column 'amount_minor' in 'field list'` | `ReportsService` queries `SUM(amount_minor)` against `vendor_payout_requests`. The schema has `requested_amount_minor` (only amount column — migration 2026_01_05_000003). | Both query sites (admin summary + per-vendor summary) now use `SUM(requested_amount_minor)`. Output field names (`pending_amount_minor`, etc) are PRESERVED — React contract unchanged. |
| §3 | Vendor order-status dropdown grayed out: all options unavailable | Show.tsx's `canDeliver = order.fulfillment_status === 'shipped'` — `'shipped'` is an ORDER STATUS, never a fulfillment_status value (enum is `['unfulfilled','partially_fulfilled','fulfilled','returned']`). `canDeliver` was always false. Plus other client-side gates referenced narrow start states. | NEW `VendorOrderController::computeStatusOptions()` private method using canonical `Order::STATUS_*` + `OrderItem::FUL_*` constants. Returns a `status_options` prop. Show.tsx reads it directly — single source of truth. NO `paid` option is exposed (vendors must not manipulate payment status, per dev §3 explicit). |
| §4 | Replying to a support ticket throws `LazyLoadingViolationException` attempting to lazy-load `[user]` on `SupportTicketMessage` | Filament admin reply action mutates state; Livewire re-renders the Infolist; `RepeatableEntry('messages')` iterates `message.user.name` — but the post-action message rows lack the eager-loaded user relation. `resolveRecord` doesn't re-run. | Filament: every mutating action (`reply`, `changeStatus`, `changePriority`, `assign`) explicitly calls `$record->load(['messages.user:id,name,email'])` after the mutation. Customer + vendor reply controllers replace `return back()` with explicit `return redirect("/tickets/{$ticket->id}")` (or vendor equivalent) — eliminates Referer ambiguity. |
| §2 | Site feels slow/laggy | `HandleInertiaRequests::share()` returned `auth.user.permissions = getAllPermissions()->pluck('name')->toArray()` on EVERY render. For an admin user with the full Phase 7 catalogue (~80 permissions), this was a Spatie DB query + plucking on every page navigation. No React page reads this prop. | Permissions removed from default share. `is_admin`, `roles`, `email`, etc. are KEPT (cheap). If a future page needs permissions, it can include them via `Inertia::lazy()` on a per-controller basis. |

## Counts

| | v10.10 → v10.11 |
|---|---|
| Phase 10 CI sub-checks | 45 → 50 |
| Phase 10 Pest scenarios | 138 → 155 |
| New PHP source files | 0 |
| Modified PHP source files | 6 |
| Modified React files | 1 (Vendor/Orders/Show.tsx — `status_options` prop) |
| New Pest test files | 1 (17 scenarios) |
| v1-v9 files touched | 0 |
| v10.0-v10.10 fix code reverted | 0 |
| Helpers added | 4 (`p1011_seed`, `p1011_admin`, `p1011_vendor_user`, `p1011_customer`) — 68 total unique, 0 duplicates |

## Required access rules — preserved

All v10.10 access rules intact:

| Role | `/admin/reports` |
|---|---|
| `super_admin` / `admin_staff` / `admin` / `administrator` (active) | 200 |
| `vendor` | 403 |
| `customer` | 403 |
| Guest | redirect /login |

## Per dev §6 + §9 acceptance

**Phase 10 v10.11 is implemented but requires developer runtime verification in the same running application.**

Dev runs:
```bash
php artisan optimize:clear
php artisan migrate
php artisan test --filter=Phase10V1011
npm run typecheck && npm run build
```

Then restarts `php artisan serve` from the active folder, hard-refreshes the browser, and walks confirmations A-D in `PHASE_10_v10.11_DEVELOPER_CHECKLIST.md`.
