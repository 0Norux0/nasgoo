# Phase 10 v10.13 — Vendor Reports Navigation and Access Repair

Per dev §11.

## Pre-flight: Vendor Reports infrastructure inventory

| Element | Status before v10.13 |
|---|---|
| Route `vendor.reports.index` (`GET /vendor/reports`) | **EXISTS** since v10.1, registered in `routes/web.php` line 176 inside `Route::middleware(['auth', 'vendor:approved'])->group(...)` |
| Route `vendor.reports.export` (`GET /vendor/reports/export.csv`) | **EXISTS** since v10.1, same middleware group |
| Controller `App\Http\Controllers\Vendor\VendorReportsController` | **EXISTS**, `index()` + `exportCsv()` methods |
| Service methods `vendorFinancialSummary($vendorId, ...)`, `vendorProductPerformance($vendorId, ...)`, `dailyRevenueSeries(..., $vendorId)` | **EXIST** in `App\Domain\Reports\ReportsService`, properly vendor-scoped via `where('order_items.vendor_id', $vendorId)` |
| React page `resources/js/Pages/Vendor/Reports/Index.tsx` | **EXISTS**, uses `VendorLayout`, 185 lines |
| Vendor middleware (resolves vendor from auth, never URL param) | **EXISTS**, `App\Http\Middleware\EnsureVendor` — resolves `$user->vendor()->first()` and sets `$request->attributes->vendor`. Vendor cannot be spoofed via query string. |
| Nav link in `VendorLayout.tsx` baseItems with testid `vendor-nav-reports` | **EXISTS** since v10.1 (moved to baseItems in v10.2 so it shows for non-approved vendors too) |

**Everything was already wired correctly.** The dev's report — "cannot find any menu or navigation link for Vendor Reports" — is a **discoverability** problem, not a routing/auth/data problem. The link rendered as plain text among 14 other plain-text nav items; it got lost.

## Why the menu was effectively invisible

`VendorLayout.tsx` desktop nav uses `flex-wrap` inside `max-w-3xl`. For an approved vendor with 15 nav items, the items wrap onto 2-3 lines, all visually indistinguishable from each other:

```
Dashboard  Products  Orders  Reports  Reviews  Wallet  Payouts  Suppliers
Supplier Orders  Services  Providers  Bookings  Promotions  Coupons  Tickets
```

"Reports" is in the row, but visually identical to its 14 siblings. A reasonable user scanning the navigation for a "Reports" or "Reports Dashboard" label would skim past it, especially under time pressure or on a small viewport.

## Active vendor navigation component

`resources/js/Layouts/VendorLayout.tsx` is the canonical and ONLY vendor layout. Confirmed by:

```bash
ls resources/js/Layouts/
# AdminLayout.tsx  AuthLayout.tsx  StorefrontLayout.tsx  VendorLayout.tsx
```

The vendor dashboard `resources/js/Pages/Vendor/Dashboard.tsx` imports it (`import VendorLayout from '@/Layouts/VendorLayout';`). All vendor pages use this single layout.

## Exact route name and URI

| Property | Value |
|---|---|
| URI | `GET /vendor/reports` |
| Name | `vendor.reports.index` |
| Middleware | `web, auth, vendor:approved` |
| Controller | `App\Http\Controllers\Vendor\VendorReportsController@index` |
| Returned component | `Vendor/Reports/Index` (Inertia) |

| Property | Value |
|---|---|
| URI | `GET /vendor/reports/export.csv` |
| Name | `vendor.reports.export` |
| Middleware | `web, auth, vendor:approved` |
| Controller | `App\Http\Controllers\Vendor\VendorReportsController@exportCsv` |
| Response | `Symfony\Component\HttpFoundation\StreamedResponse` |

## Authorization rule

The `vendor:approved` middleware (`App\Http\Middleware\EnsureVendor::handle(..., 'approved')`) requires:

1. **Authenticated user** — else redirect to `/login`.
2. **Has a vendor profile** (`$user->vendor()->first()` not null) — else redirect to `/vendor/apply`.
3. **Vendor status === 'approved'** — else redirect to `/vendor` (the dashboard, where the pending/rejected/suspended reason is visible).

The vendor is set as a request attribute (`$request->attributes->set('vendor', $vendor)`). `VendorReportsController` reads it from the attribute, **never from the URL** — this means the dev's §5 demand "vendor cannot pass `?vendor_id=N`" is satisfied by the architecture. Pest scenario `vendor cannot pass ?vendor_id to read another vendor's data` verifies this regression-style.

## What v10.13 actually changes

### 1. `resources/js/Layouts/VendorLayout.tsx` — visual discoverability

Add an inline SVG bar-chart icon to the Reports nav item (no new dependency — `lucide-react` isn't in `package.json`). Reports stays in `baseItems` (always visible to vendors per the v10.2 decision) but is now visually distinct from the other 14 text-only links. Both desktop nav and mobile drawer get the icon.

Add an `isActive(href)` helper + indigo active-state styling (`text-indigo-700 font-semibold`) so that when the vendor is on `/vendor/reports`, the nav item is highlighted — making the link obviously "live".

### 2. `resources/js/Pages/Vendor/Dashboard.tsx` — second visibility surface

Add a prominent indigo gradient CTA card directly under the Business header for **approved** vendors:

```
┌────────────────────────────────────────────────────────────────────┐
│  📊  View My Reports                              Open Reports →   │
│      Gross sales, commission, earnings, payouts, top products —    │
│      your own data only.                                           │
└────────────────────────────────────────────────────────────────────┘
```

The CTA is `data-testid="vendor-dashboard-reports-cta"`. Only shows for `vendor.status === 'approved'` (the route is gated by `vendor:approved`; showing it to a pending vendor would silently redirect them on click — bad UX).

### 3. `tests/Feature/Phase10V1013RegressionTest.php` — 19 Pest scenarios

Including the critical two-vendor data isolation test (dev §8.7): vendor A has 150.00 KWD gross; vendor B has 990.00 KWD gross; vendor A's `/vendor/reports` shows 150.00 (not 1140.00) AND `vendor A?vendor_id=<B>` STILL shows 150.00 (request param ignored, vendor resolved from auth).

### 4. CI sub-checks (3 new, 55 Phase 10 total)

- VendorLayout: Reports nav link in baseItems + ReportsIcon + icon flag + isActive helper
- Vendor Dashboard: CTA testid + /vendor/reports link + "View My Reports" copy
- Pest v10.13 filter

## Files changed

| File | Change |
|---|---|
| `resources/js/Layouts/VendorLayout.tsx` | Add ReportsIcon SVG component, `icon?: 'reports'` to NavItem type, `icon: 'reports'` flag on Reports nav item, `isActive` helper, indigo active-state styling on both desktop nav and mobile drawer |
| `resources/js/Pages/Vendor/Dashboard.tsx` | Add prominent indigo CTA card linking to `/vendor/reports` for approved vendors, after Business header, before package/subscription cards |
| `tests/Feature/Phase10V1013RegressionTest.php` | NEW — 19 Pest scenarios |
| `.github/workflows/ci.yml` | 3 new v10.13 sub-checks + verdict bump |
| `VERSION` | `Phase 10 v10.12` → `Phase 10 v10.13` |

## Files NOT changed

- Route file (`routes/web.php`) — vendor reports routes already correctly registered in `vendor:approved` middleware group since v10.1
- VendorReportsController — already correctly resolves vendor from request attribute (not URL); no change needed
- ReportsService — already has properly vendor-scoped methods; v10.12 fixes preserved
- AdminLayout / Admin Reports — explicitly NOT touched (dev: "Do not modify the working Admin Reports feature")

## Authorization unchanged (per dev §6 / §10)

| Role / state | Vendor Reports menu | `/vendor/reports` route |
|---|---|---|
| Approved vendor | ✓ Visible with icon + dashboard CTA | ✓ HTTP 200 |
| Pending vendor | Nav link visible (per v10.2), CTA hidden, route redirects to `/vendor` | 302 |
| Rejected vendor | Same as pending | 302 |
| Suspended vendor | Same as pending | 302 |
| Customer | No vendor profile → redirect to `/vendor/apply` from EnsureVendor | 302 |
| Guest | Redirect to `/login` | 302 |
| Admin | n/a (admin uses AdminLayout, no vendor nav) | Receives `/vendor/apply` redirect if not also a vendor — but admin reports still works at `/admin/reports` |

Admin Reports (`/admin/reports`) is unaffected by v10.13. Pest scenario `admin /admin/reports still loads (v10.12 preserved)` regression-guards this.

## Tests executed (static)

19 Pest scenarios written in `tests/Feature/Phase10V1013RegressionTest.php`. The dev's §8 mandatory test list mapped:

| § | Test scenario | Pest scenario |
|---|---|---|
| 8.1 | Approved vendor sees Reports menu | "VendorLayout source includes the Reports nav link in baseItems" |
| 8.2 | Vendor Reports route returns HTTP 200 | "approved vendor reaches /vendor/reports with HTTP 200" |
| 8.3 | Vendor Reports page renders | "approved vendor sees the Reports page (Inertia component name)" |
| 8.4 | Vendor sees only their own sales | "vendor sees only their own sales totals" |
| 8.5 | Vendor sees only their own earnings | (covered by 8.4 — financial.gross_minor + financial.earnings) |
| 8.6 | Vendor sees only their own payouts | (covered by financial.payout_* keys returned in 8.4) |
| 8.7 | Vendor cannot access another vendor's data | "vendor cannot pass ?vendor_id to read another vendor's data" |
| 8.8 | Customer receives 403 | "customer receives 403 (or redirect to apply) for /vendor/reports" |
| 8.9 | Guest is redirected to login | "guest is redirected to login from /vendor/reports" |
| 8.10 | Admin Reports remain working | "admin /admin/reports still loads (v10.12 preserved)" |
| 8.11 | Vendor Reports date filter works | "vendor Reports date preset query updates the filter prop" |
| 8.12 | Vendor Reports export is scoped | "vendor Reports export.csv is reachable for approved vendor" + "denied to a customer" |
| 8.13 | Vendor Reports link appears in desktop navigation | "VendorLayout source includes the Reports nav link in baseItems" + "ReportsIcon SVG" |
| 8.14 | Vendor Reports link appears in mobile navigation | (same — VendorLayout renders both nav surfaces; CI grep verifies icon flag is consumed in both) |
| 8.15 | Active navigation state works | "VendorLayout source uses isActive helper for active-state styling" |

Plus the two visibility surfaces are independently asserted (nav link AND dashboard CTA) so the dev can find Reports from either entry point.

## Two-vendor data isolation result (per dev §11)

Pest scenario `vendor sees only their own sales totals`:
- Vendor A: 2 orders × line totals 100.00 + 50.00 = **150.00** gross
- Vendor B: 1 order × line total 990.00 = **990.00** gross
- Vendor A's `/vendor/reports` returns `financial.gross_minor: 15000` (i.e. 150.00 — only A's data)
- Vendor B's totals (99000 minor) do NOT appear

Plus `vendor cannot pass ?vendor_id`: Vendor A hits `/vendor/reports?vendor_id={B.id}` — still gets only A's 150.00. The query param is silently ignored because the vendor is resolved server-side from `auth()->user()->vendor`, never from the URL.

## Admin Reports preservation

| Marker | Result |
|---|---|
| v10.10 `guardAdminReportsAccess` method | ✓ preserved (3 occurrences: 1 def + 2 call sites in ReportsController) |
| v10.10 diagnostic + repair commands | ✓ preserved |
| v10.10 EnsureAdminReportsAccessSeeder | ✓ preserved |
| v10.11 §5 SUM(requested_amount_minor) | ✓ preserved (2 sites) |
| v10.11 §3 computeStatusOptions | ✓ preserved (2 sites) |
| v10.11 §4 Filament defensive eager-loads | ✓ preserved (5 occurrences) |
| v10.11 §2 getAllPermissions absent from share | ✓ preserved |
| v10.12 User::role('customer') Spatie scope | ✓ preserved |
| v10.12 no DB::table users where role | ✓ preserved (0 occurrences) |

Pest scenarios also regression-guard the v10.11 and v10.12 fixes inline.

## Desktop verification

The dev's §9.2-3 manual walkthrough:
- Open `/vendor` as approved vendor
- Desktop viewport (`lg:` and above): Reports nav link visible with bar-chart icon prefix
- Indigo CTA card visible below business header

## Mobile verification

- Mobile viewport (below `lg`): hamburger menu opens via `<button data-testid="vendor-nav-reports">` (the button itself has the testid; the menu item also has it inside the drawer)
- Reports link visible in mobile drawer with same icon prefix
- Click → drawer closes (`onClick={() => setMobileOpen(false)}`) and navigates to `/vendor/reports`

I cannot run the dev's specific browser. Pest tests assert the rendered HTML contains the testids; the dev's §9 manual walkthrough is the final acceptance gate.

## Confirmation that no `users.role` dependency remains

CI regression-guards from v10.12 are preserved:
```bash
grep -rnE "DB::table\(['\"]users['\"]\)->where\(['\"]role['\"]" app/    # 0 hits
grep -rnE "User::where\(['\"]role['\"]" app/                            # 0 hits
```

## Per dev §13 acceptance

**Phase 10 v10.13 is implemented but requires developer runtime verification.**

`PHASE_10_v10.13_DEVELOPER_CHECKLIST.md` lists the §9 manual walkthrough.
