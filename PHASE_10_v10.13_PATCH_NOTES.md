# Phase 10 v10.13 — Patch Notes

## What's fixed

| Defect | Root cause | Fix |
|---|---|---|
| Approved vendors couldn't find the Reports menu link in the vendor navigation. | The Reports nav link **was already rendered** (in `baseItems` of `VendorLayout.tsx` since v10.1, moved to baseItems in v10.2). The route, controller, ReportsService methods, and React page **all existed and worked**. The route was correctly gated by `vendor:approved` middleware; vendor data isolation was correctly enforced via `$request->attributes->get('vendor')` (no URL-param spoofing possible). The problem was purely **visual discoverability** — the link rendered as plain text identical to its 14 sibling nav items (Dashboard, Products, Orders, Reports, Reviews, Wallet, Payouts, Suppliers, Supplier Orders, Services, Providers, Bookings, Promotions, Coupons, Tickets). A user scanning a 15-item nav bar reasonably misses one text label among 14 others. | **Two new visibility surfaces, no route or controller changes:** (1) `VendorLayout.tsx` — added an inline SVG bar-chart icon prefix to the Reports nav item (no new dependency; `lucide-react` isn't in `package.json`), `isActive(href)` helper, and indigo "active" state styling for both desktop nav and mobile drawer. Reports stays in `baseItems` (per the v10.2 decision — always visible to vendors, including non-approved ones, since v10.2's intent was to ensure visibility regardless of approval state). (2) `Vendor/Dashboard.tsx` — added a prominent indigo gradient CTA card directly under the Business header for approved vendors only, with testid `vendor-dashboard-reports-cta`. Two-way discoverability: vendor can reach Reports from the nav OR from the dashboard CTA. |

## Counts

| | v10.12 → v10.13 |
|---|---|
| Phase 10 CI sub-checks | 52 → 55 |
| Phase 10 Pest scenarios | 170 → 189 |
| PHP source files modified | 0 |
| React files modified | 2 (`VendorLayout.tsx`, `Vendor/Dashboard.tsx`) |
| New files | 1 (Pest test file) |
| v1-v9 files touched | 0 |
| v10.0-v10.12 fix code reverted | 0 |
| Helpers added | 6 (`p1013_seed/approved_vendor/pending_vendor/customer/admin/seed_order_for_vendor`) — 77 total unique, 0 duplicates |

## What was NOT changed

Per dev "Do not modify the working Admin Reports feature":
- No changes to `AdminLayout.tsx`
- No changes to `Admin/Reports/Index.tsx`
- No changes to `ReportsController` (admin) or its `guardAdminReportsAccess` method
- No changes to `ReportsService::adminPayoutSummary` (v10.11 fix preserved)
- No changes to `ReportsService::marketplaceCounts` (v10.12 fix preserved)

Per scope discipline:
- No changes to routes (vendor reports routes already correctly registered since v10.1)
- No changes to `VendorReportsController` (already correctly resolves vendor from `$request->attributes`, not URL)
- No changes to `ReportsService::vendorFinancialSummary` (already properly vendor-scoped via `where('order_items.vendor_id', $vendorId)`)

The dev's report was about navigation discoverability. The data layer was correct.

## Access rules — preserved exactly

| Role / state | Vendor Reports menu | `/vendor/reports` route |
|---|---|---|
| Approved vendor | ✓ visible with icon + dashboard CTA | 200 |
| Pending / rejected / suspended vendor | Nav link visible (per v10.2), CTA hidden, route 302 → `/vendor` | 302 |
| Customer (no vendor profile) | No vendor nav at all (different layout) | 302 → `/vendor/apply` |
| Guest | n/a | 302 → `/login` |

Admin Reports (`/admin/reports`) is completely untouched — Pest scenario regression-guards.

## Vendor data isolation — regression-tested

The most important Pest scenarios:
- "vendor sees only their own sales totals": Vendor A has 150.00 KWD; Vendor B has 990.00 KWD; A's report shows 150.00 (NOT 1140.00)
- "vendor cannot pass `?vendor_id` to read another vendor's data": injecting `?vendor_id=<B>` still returns only A's data — vendor is resolved server-side from `auth()->user()->vendor`, never from the URL

## Per dev §13 acceptance

**Phase 10 v10.13 is implemented but requires developer runtime verification.**

Dev runs:
```bash
php artisan optimize:clear
php artisan route:list | grep -i report
php artisan test --filter='Phase10V1013'
npm run typecheck && npm run build
```

Then logs in as an approved vendor and walks the §9 confirmations in `PHASE_10_v10.13_DEVELOPER_CHECKLIST.md`. The Reports link should now be obviously visible in two places: the navigation (with icon + active state when on the Reports page) AND the dashboard (prominent indigo CTA card).
