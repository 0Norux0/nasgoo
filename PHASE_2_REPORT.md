# Phase 2 — Vendor System: Completion Report

**Version:** v3.0
**Built on:** Phase 1 v2.0 base (verified ✅)
**Status:** Ready for GitHub Actions verification

---

## 1. What was completed

### 1.1 Vendor registration
- ✅ Public form at `/vendor/apply` (auth required — guests are redirected to register first)
- ✅ Captures all 20+ spec fields: business identity, owner, location, legal IDs, payout method, documents, package selection, T&C acceptance
- ✅ File uploads (logo / banner / license / ID) validated for type (PDF/JPG/PNG/WEBP) and size (2–5 MB)
- ✅ Files stored under `vendors/{vendor_id}/...` on the default disk (R2 in prod, MinIO in dev)
- ✅ Submission creates a `Vendor` with `status=pending` plus a `VendorSubscription` with `status=pending` bound to the chosen package
- ✅ Existing vendors are auto-redirected to their dashboard
- ✅ Audit log entry written: `vendor.application_submitted`

### 1.2 Models & tables
| Table | Migration | Model | Factory |
|---|---|---|---|
| `vendors` | `2026_01_02_000001_create_vendors_table.php` | `Vendor` | ✅ |
| `vendor_packages` | `_000002_create_vendor_packages_table.php` | `VendorPackage` | ✅ |
| `vendor_subscriptions` | `_000003_create_vendor_subscriptions_table.php` | `VendorSubscription` | — (not needed) |
| `vendor_commission_rules` | `_000004_create_vendor_commission_rules_table.php` | `VendorCommissionRule` | — (not needed) |

Highlights:
- `Vendor.payout_details` uses `encrypted:array` cast (Laravel APP_KEY)
- `Vendor.slug` auto-generated on create with collision-safe uniqueness check
- `Vendor` uses `LogsActivity` and `SoftDeletes`
- `User::vendor()` HasOne relationship added (Phase 2)

### 1.3 Vendor packages — 3 seeded defaults

| Slug | Name | Price | Max products | Video | 3D | Dropshipping | Customization | Featured | Commission |
|---|---|---|---|---|---|---|---|---|---|
| `basic` | Basic Vendor | 0 KWD | 25 | ❌ | ❌ | ❌ | ❌ | ❌ | **30%** |
| `standard` | Standard Vendor | 5.000 KWD/mo | 200 | ✅ | ❌ | ❌ | ❌ | ❌ | **20%** |
| `professional` | Professional Vendor | 25.000 KWD/mo | ∞ | ✅ | ✅ | ✅ | ✅ | ✅ | **10%** |

Seeder: `database/seeders/VendorPackagesSeeder.php` (added to `DatabaseSeeder` chain).

### 1.4 Vendor subscriptions
- `VendorSubscription` model with status constants (`active`, `expired`, `cancelled`, `grace`, `pending`)
- `$vendor->activeSubscription` HasOne relation that filters to `status=active`
- `isActive()` helper accounts for `ends_at` future-check
- Subscription end date computed from `package.billing_cycle`: monthly → +1 month, yearly → +1 year, lifetime → null

### 1.5 Vendor approval workflow
Encapsulated in `App\Domain\Vendor\VendorApprovalService` — wraps all side effects in `DB::transaction`:

**On approve:**
1. Flip `status` → `approved`, set `approved_at`, `approved_by`
2. Assign `vendor` role to the user (idempotent)
3. Create active `VendorSubscription` (if none exists) with computed `ends_at`
4. Create default vendor-scoped `VendorCommissionRule` (priority 50) using `package.default_admin_commission_percent`
5. Write `vendor.approved` audit log entry
6. Dispatch `VendorApprovedNotification` (best-effort — failures don't roll back)

**On reject / suspend / reopen:** status flip + audit log + notification

Filament `VendorResource` exposes 4 inline actions visible based on status + permission:
- **Approve** (only when pending + `vendors.approve`) — picker form for package selection
- **Reject** (only when pending + `vendors.approve`) — reason textarea
- **Suspend** (only when approved + `vendors.suspend`) — optional reason
- **Reopen** (only when rejected/suspended/closed + `vendors.approve`)

### 1.6 Commission rules — foundation
`App\Domain\Commission\CommissionResolver`:
- `resolve(Vendor, context, when)` — returns the applicable rule
- `compute(Vendor, Money, context)` — returns commission `Money`

Resolution priority (lower wins):
1. **product** (deferred to Phase 3 — catalog doesn't exist yet)
2. **category** (Phase 3)
3. **vendor** (Phase 2 — created on approval at priority 50)
4. **package** (Phase 2 — via `package.default_admin_commission_percent` fallback)
5. **global** (Phase 2 — admin can create at priority 100)

Supports: `percent`, `fixed`, `fixed_plus_percent`. Honors `effective_from` / `effective_until` windows.

### 1.7 Vendor dashboard (`/vendor`)
- Status-aware banner: pending / approved / rejected (shows reason) / suspended / closed
- Business header card
- Current package card (name, billing cycle, analytics level, limits)
- Subscription card (status, dates)
- Commission card (rule scope + value)
- Allowed features grid (live from `package.featureFlags()`)
- Profile completion progress (% of 7 optional fields filled)
- Public storefront link (only for approved)

### 1.8 Vendor profile editing (`/vendor/profile`)
- Approved-only (middleware `vendor:approved`)
- Edit: business name/phone/description, country/city/address, payout method, logo, banner
- Payout details changes write a separate `vendor.payout_details_updated` audit log
- Logo/banner uploads write `vendor.logo_updated` / `vendor.banner_updated` audit logs
- Always writes `vendor.profile_updated` audit log

### 1.9 Filament admin resources

| Resource | Path | Highlights |
|---|---|---|
| `VendorResource` | `/admin/vendors` | Status badge, navigation badge with pending count, 4 inline actions, default filter `status=pending`, View/List/Create/Edit pages |
| `VendorPackageResource` | `/admin/vendor-packages` | Full feature matrix toggles, commission %, subscriber count, formatted price |
| `VendorSubscriptionResource` | `/admin/vendor-subscriptions` | Status pill colors, package + status filters |
| `VendorCommissionRuleResource` | `/admin/vendor-commission-rules` | Live commission-type switching (percent vs fixed fields), scope filter |

All resources nested under "Marketplace" navigation group (added to `AdminPanelProvider`).

### 1.10 Public vendor storefront (`/vendors/{slug}`)
- Only approved vendors visible; others return 404
- Banner + logo placeholder, business name, location, rating + sales summary
- "Products coming soon" + "Services coming soon" panels (Phase 3 will fill)
- Top bar adapts to auth state (guest / customer / vendor / admin)

### 1.11 Notifications
Uses Phase 1 `notification_templates` table with locale + channel fallback to English.

7 new event keys added to `NotificationTemplate::supportedEventKeys()` and seeded with en + ar templates:
- `vendor.application_submitted`
- `vendor.approved` ✅ (notification class wired)
- `vendor.rejected` ✅ (notification class wired)
- `vendor.suspended` ✅ (notification class wired)
- `vendor.subscription_activated` (template seeded; class deferred — no payment flow yet)
- `vendor.package_changed` (template seeded; admin-driven, no dispatcher yet)
- `vendor.commission_changed` (template seeded; admin-driven, no dispatcher yet)

### 1.12 Audit logging
Sensitive actions automatically logged via `AuditLogger`:
- `vendor.application_submitted` (in `VendorRegistrationController@store`)
- `vendor.approved` / `vendor.rejected` / `vendor.suspended` / `vendor.reopened` (in `VendorApprovalService`)
- `vendor.profile_updated`, `vendor.payout_details_updated`, `vendor.logo_updated`, `vendor.banner_updated` (in `VendorDashboardController@updateProfile`)

### 1.13 Authorization & security
- 4 policies registered: `VendorPolicy`, `VendorPackagePolicy`, `VendorSubscriptionPolicy`, `VendorCommissionRulePolicy`
- All have `before()` super-admin bypass
- `EnsureVendor` middleware (`vendor` alias) — supports `vendor:approved` parameter to restrict approved-only routes
- File upload validation: `mimes:pdf,jpg,jpeg,png,webp`, `max:2048-5120` KB
- `payout_details` cast `encrypted:array` — Laravel encrypts with `APP_KEY`
- New permission added: `vendor_subscriptions.manage` (super_admin only; explicitly excluded from admin_staff)

### 1.14 Tests (6 new files, ~30 scenarios)

| File | Scenarios |
|---|---|
| `VendorApplicationTest` | Authenticated submit, pending sub created, terms required, existing-vendor redirect (4) |
| `VendorApprovalTest` | Status flip, role assignment, subscription creation, default commission rule, audit log entries, rejection reason saved (6) |
| `VendorDashboardAccessTest` | Guest redirect, no-vendor redirect, approved access, pending limited, suspended blocked from profile, customer blocked (6) |
| `VendorPackageAndStorefrontTest` | 3 packages seeded with right slugs/percentages, feature matrix correctness, storefront 200/404 for approved/pending/suspended/missing (6) |
| `CommissionResolverTest` | Vendor rule wins by priority, returns null when no rule, honors effective_from window, computes percent + fixed_plus_percent (5) |
| `VendorFilamentAccessTest` | super_admin access, vendor blocked from admin, customer blocked (3) |

### 1.15 CI updates
- Workflow renamed `Phase 1 Verification` → `Phase 2 Verification`
- Tinker verification now also checks: 3 vendor packages exist with slugs `basic/standard/professional` AND commission percentages 30/20/10
- Notification template event-count check raised from `>=15` to `>=20`
- Final verdict: ✅ **Phase 2 PASSES — ready to approve Phase 3**

---

## 2. Demo workflow

Once CI is green, walk this in your Codespace / local:

1. Sign out, register a fresh customer at `/register` (or log in as `customer@marketplace.test`)
2. Click **"Become a vendor"** on the home page → arrives at `/vendor/apply`
3. Fill form, pick **Basic Vendor** package, accept T&Cs, submit
4. Lands on `/vendor` — sees pending banner + business header
5. Log out, log in as `admin@marketplace.test`
6. Sidebar → **Marketplace → Vendors** — pending badge shows "1"
7. Click the row → **Approve** action → pick **Standard Vendor** package → confirm
8. Notification shows "Vendor approved"
9. Log out, log back in as the customer — `/vendor` now shows approved state, package = Standard, commission = 20%, subscription active
10. `/vendor/profile` is now reachable — edit description and save
11. `/vendors/{slug}` (use the slug from the dashboard) shows the public storefront

---

## 3. Verification checklist

- [ ] CI workflow shows `✅ Phase 2 PASSES — ready to approve Phase 3`
- [ ] Admin panel sidebar has new **Marketplace** group with **Vendors / Vendor Packages / Vendor Subscriptions / Commission Rules**
- [ ] **Vendors** nav badge shows pending count
- [ ] **Vendor Packages** shows 3 rows (basic / standard / professional)
- [ ] Approve action creates subscription + commission rule + assigns role
- [ ] Rejection requires a reason
- [ ] `/vendor` dashboard adapts: pending banner / approved cards / rejected reason
- [ ] `/vendor/profile` blocked for pending vendors (redirects to `/vendor`)
- [ ] `/vendors/{slug}` 200 for approved, 404 for pending/rejected/suspended
- [ ] Audit Logs view shows `vendor.approved` / `vendor.application_submitted` entries

---

## 4. Known limitations (intentional — deferred to later phases)

| Item | Reason / Phase |
|---|---|
| Payment processing for paid packages | Phase 4 (Tap / MyFatoorah / Stripe). Subscriptions are admin-granted for free for now. |
| Product CRUD for approved vendors | Phase 3 — the catalog tables don't exist yet |
| Document file viewer in admin (PDFs/images inline) | Path fields shown; preview is a polish item for Phase 9 |
| Vendor analytics graphs on dashboard | Empty placeholders for now; data shows up in Phase 3+ once orders exist |
| Notification dispatchers for `subscription_activated` / `package_changed` / `commission_changed` | Templates seeded; admin-side dispatchers added when the underlying actions happen (subscription created via payment in Phase 4; commission update in admin UI) |
| Bank/payout integration | `payout_details` accepted and encrypted, but no bank API integration |
| 2FA on vendor accounts | Schema reserved in Phase 1; UI in Phase 9 |
| Vendor email verification gate (force verify before approve) | Soft enforcement only — admin can approve unverified |

None of these block Phase 3.

---

## 5. Next step

Reply **"approve Phase 3"** after CI is green and the verification checklist passes.

Phase 3 scope (per the locked plan): **Product Marketplace / Catalog**
- `products`, `product_variants`, `product_images`, `categories` tables
- Product CRUD for approved vendors (gated by their `VendorPackage.max_products` + feature flags)
- Admin product approval workflow
- Public product browse + detail pages
- Product-scoped commission rule resolution (Phase 2 left this stub)

**I will not start Phase 3 until you explicitly approve.**
