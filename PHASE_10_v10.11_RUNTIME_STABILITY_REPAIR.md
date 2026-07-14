# Phase 10 v10.11 — Runtime Stability Repair

Per dev §7. Four confirmed defects, each with reproduction, root cause, files changed, test, and runtime expectation.

## Per-defect summary table

| # | Issue | Reproduced | Root cause | Active files changed | Test performed | Runtime result |
|---|---|---|---|---|---|---|
| §2 | Site feels slow/laggy | Static analysis of `HandleInertiaRequests::share()` shows `getAllPermissions()->pluck('name')->toArray()` runs on EVERY Inertia render | Spatie `getAllPermissions()` pulls direct + via-role permissions, hits cache or queries 80+ rows for admin users, plus pluck and array conversion. Most React pages never read `auth.user.permissions`. | `app/Http/Middleware/HandleInertiaRequests.php` | Pest: `HandleInertiaRequests no longer calls getAllPermissions on every request` + `admin can still read auth.user.is_admin from shared props` | Pending dev runtime confirmation: subjective page-load improvement on admin pages; admin still sees is_admin/roles |
| §3 | Vendor order-status dropdown grayed out | `resources/js/Pages/Vendor/Orders/Show.tsx` computed availability with `order.fulfillment_status === 'shipped'` — 'shipped' is an ORDER STATUS, never a fulfillment_status (enum is `['unfulfilled','partially_fulfilled','fulfilled','returned']`). `canDeliver` was always false. | Client-side rules referenced non-existent enum values | `app/Http/Controllers/Vendor/VendorOrderController.php`, `resources/js/Pages/Vendor/Orders/Show.tsx` | Pest: status_options prop exists; correct option marked available for paid/confirmed/shipped orders; no 'shipped' fulfillment_status check remains | Pending dev runtime confirmation: dropdown options become enabled for vendor's real order states |
| §4 | Support ticket reply lazy-load violation | Filament admin reply action mutates state; Livewire re-renders Infolist; `$record->messages` is re-read after action without 'user' eager-loaded; `RepeatableEntry('messages')` iterates message.user.name → LazyLoadingViolationException | Filament's `resolveRecord` eager-loads at mount; post-action re-render does NOT re-run resolveRecord, so the newly created message row loads without user relation | `app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php` (4 action callbacks), `app/Http/Controllers/SupportTicketController.php`, `app/Http/Controllers/Vendor/VendorSupportTicketController.php` | Pest with `Model::preventLazyLoading(true)`: customer + vendor show return 200; reply controllers explicitly redirect to canonical show URL; Filament source has ≥4 defensive eager-loads | Pending dev runtime confirmation: replying as admin/customer/vendor no longer throws |
| §5 | `/admin/reports` SQL `Unknown column 'amount_minor'` | `app/Domain/Reports/ReportsService.php` queried `SUM(amount_minor)` against `vendor_payout_requests` | Schema has `requested_amount_minor` (only amount column — see migration 2026_01_05_000003) | `app/Domain/Reports/ReportsService.php` (2 query sites: admin summary + per-vendor) | Pest: admin reports loads with no SQL error on empty payouts AND with seeded payouts of all 4 statuses; ReportsService source no longer contains `SUM(amount_minor)`; pending_amount_minor returns the seeded amount | Pending dev runtime confirmation: HTTP 200 on /admin/reports + payout cards populate |

## §1 — Project-folder confirmation (the dev's pre-check)

I cannot run the dev's `php artisan about` etc. against their installation. Static commands the dev should run:

```bash
pwd                                                      # → expected: their project folder
cat VERSION                                              # → expected: Phase 10 v10.11
php artisan route:list --path=admin/reports              # → 2 routes (index + export)
php artisan route:list --path=vendor/orders              # → ~10 routes including show/confirm/ship/deliver
npm run build                                            # → succeeds; emits new manifest.json
```

Verify the browser is loading the freshly built assets, not a cached older bundle: open dev tools Network tab, confirm the `.js` and `.css` filenames match `public/build/manifest.json`. Hard-refresh (Ctrl+Shift+R) if mismatched.

## §2 — Performance — actual change

### What was happening on every Inertia render

`HandleInertiaRequests::share()` returned an `'auth.user'` prop whose value included:

```php
'permissions' => $request->user()->getAllPermissions()->pluck('name')->toArray(),
```

`getAllPermissions()` walks the user's direct permissions AND inherited permissions through roles. For an admin with the full Phase 7 catalogue (≈80 permissions across users/roles/settings/vendors/products/services/orders/payments/bookings/reviews/promotions/support/reports/audit/supplier_platforms/supplier_integrations/supplier_products/supplier_orders/customization_fields/customization_proofs), this is one Spatie query (or two if the cache is cold) plus pluck+array conversion. Across an admin session this happens on every page load.

### What v10.11 does

Remove `permissions` from the default share. Keep:
- `auth.user.id`, `name`, `email`, `email_verified`
- `auth.user.roles` (single getRoleNames query)
- `auth.user.is_admin` (single hasAnyRole call)
- `auth.user.vendor_status` (already lazy via property access)

No React page in the repo references `auth.user.permissions` — I grep'd. So removing it from the share has no behavioral effect.

### Other props inspected, kept

- `cart_summary` — closure, only fires for authenticated users; cheap (cart + counts via column on cart model)
- `top_categories` — `Cache::remember(... 1 hour ...)` ✓
- `translations` — `Cache::remember(... 1 hour ...)` ✓
- `seo` — closure with array merge, no DB
- `flash.*` — session reads ✓

I did not introduce changes to controllers' query patterns outside the dev's confirmed defects, per dev "do not modify unrelated working features".

## §3 — Vendor order dropdown — the bug and the fix

### The bug

`resources/js/Pages/Vendor/Orders/Show.tsx` (pre-v10.11) computed:

```ts
const canShip = order.payment_status === 'paid' && order.items.some((i) => i.fulfillment === 'unfulfilled');
const canConfirm = order.status === 'pending_payment' || order.status === 'paid';
const canDeliver = order.fulfillment_status === 'shipped' || order.fulfillment_status === 'partially_shipped';
```

The third line is wrong: `order.fulfillment_status` is an enum with values `['unfulfilled', 'partially_fulfilled', 'fulfilled', 'returned']` (see `Order::FUL_*` constants). The values `'shipped'` and `'partially_shipped'` are ORDER statuses, not fulfillment statuses. `canDeliver` was always false. Plus `canConfirm` and `canShip` had overly narrow start states.

### The fix

Move availability computation to `VendorOrderController::computeStatusOptions(Order, vendorId)` — the controller uses canonical enum constants directly (`Order::STATUS_PAID`, `Order::STATUS_SHIPPED`, `OrderItem::FUL_UNFULFILLED`, etc) so the rules can never drift from the schema.

The server returns a `status_options` prop, an array of `{value, label, available, reason}`. Show.tsx reads it directly:

```ts
const availability = Object.fromEntries(status_options.map((o) => [o.value, o.available]));
const canConfirm = availability['confirm'] ?? false;
const canShip = availability['ship'] ?? false;
const canDeliver = availability['deliver'] ?? false;
```

The side buttons (Confirm / Ship / Deliver) AND the dropdown now read from the same source of truth.

### Active route verification

```
POST  vendor/orders/{order}/confirm    → vendor.orders.confirm   → VendorOrderController@confirm
POST  vendor/orders/{order}/ship       → vendor.orders.ship      → VendorOrderController@ship
POST  vendor/orders/{order}/deliver    → vendor.orders.deliver   → VendorOrderController@deliver
```

Middleware: `auth, role:vendor, ensure-vendor`. The vendor middleware sets `$request->attributes->vendor`. The controller scopes order lookup to `Order::forVendor($vendor->id)`.

### Payment vs. fulfillment safety

The dropdown deliberately exposes only `confirm`, `ship`, `deliver`. There is NO `paid` option — vendors cannot mark payment status. Payment status remains rendered read-only above the dropdown. Per dev §3 explicit requirement.

## §4 — Support ticket reply lazy load — full audit

### Audit table per dev §4

| Entry point | Pre-v10.11 reload of `messages.user` after reply | v10.11 state |
|---|---|---|
| Customer show (`/tickets/{id}`) | ✓ eager-loaded via `$ticket->load(['messages.user:id,name', ...])` | unchanged |
| Customer reply (`POST /tickets/{id}/reply`) | `return back()` — relies on Referer | **v10.11**: explicit `redirect("/tickets/{$ticket->id}")` to canonical show URL |
| Vendor show (`/vendor/tickets/{id}`) | ✓ eager-loaded | unchanged |
| Vendor reply (`POST /vendor/tickets/{id}/reply`) | `return back()` | **v10.11**: explicit `redirect("/vendor/tickets/{$ticket->id}")` |
| Filament admin show | ✓ eager-loaded via `resolveRecord` | unchanged |
| Filament admin Reply action | **Bug**: action mutates state; Livewire re-renders Infolist; `$record->messages` is re-read without `user` → lazy-load violation | **v10.11**: action callback explicitly does `$record->load(['messages.user:id,name,email'])` after `$svc->reply(...)` |
| Filament admin changeStatus action | Same risk | Same fix |
| Filament admin changePriority action | Same risk | Same fix |
| Filament admin assign action | Same risk | Same fix |

### Support ticket relation-loading chain

```
SupportTicket
  ├── messages (HasMany, ordered by created_at)
  │     ├── user (BelongsTo) ← the lazy-load surface
  │     └── (attachments are in 'attachments' JSON, not a relation)
  ├── user (BelongsTo, the ticket reporter)
  ├── vendor (BelongsTo, optional)
  ├── order (BelongsTo, optional)
  ├── booking (BelongsTo, optional)
  ├── product (BelongsTo, optional)
  └── assignee (BelongsTo User, optional)
```

The canonical eager-load incantation used everywhere (controllers + Filament resolveRecord + post-action defensive loads):

```php
['messages.user:id,name,email', 'order:id,number', 'vendor:id,business_name', 'user:id,name,email', 'assignee:id,name,email']
```

## §5 — Payout column — actual schema vs. failing query

### Actual schema (the source of truth)

`database/migrations/2026_01_05_000003_create_vendor_payout_requests_table.php`:

```php
$table->id();
$table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
$table->unsignedInteger('requested_amount_minor');       // ← only amount column
$table->string('currency', 3)->default('KWD');
$table->string('status', 20)->default('pending');         // pending|approved|rejected|paid
$table->string('payout_method', 40)->default('bank_transfer');
$table->json('payout_details')->nullable();
$table->string('admin_notes', 500)->nullable();
$table->string('rejection_reason', 500)->nullable();
$table->string('transfer_reference', 120)->nullable();
$table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('requested_at');
$table->timestamp('approved_at')->nullable();
$table->timestamp('rejected_at')->nullable();
$table->timestamp('paid_at')->nullable();
$table->timestamps();
```

The model `App\Models\VendorPayoutRequest` has `requested_amount_minor` in `$fillable`. There is no `amount_minor`, no `approved_amount_minor`, no `paid_amount_minor`. The single amount column represents the amount the vendor requested; status transitions (approved/paid/rejected) don't change the source amount — only the meaning. So all status buckets aggregate the same column.

### The failing query (pre-v10.11)

```sql
SELECT status, COALESCE(SUM(amount_minor), 0) as amount_sum, COUNT(*) as cnt
FROM vendor_payout_requests
WHERE created_at BETWEEN ? AND ?
GROUP BY status;
```

MySQL returned: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'amount_minor'`.

### The fix

Replace `SUM(amount_minor)` with `SUM(requested_amount_minor)` in the 2 query sites (admin summary + per-vendor summary). The output array key names (`pending_amount_minor`, `approved_amount_minor`, `paid_amount_minor`, `rejected_amount_minor`) are PRESERVED — they're React contract fields, not column names.

### MySQL compatibility

`COALESCE(SUM(col), 0)` and `GROUP BY` are standard SQL — fully MySQL-compatible. No vendor-specific syntax introduced.

## Required automated test results (static)

- 17 Pest scenarios written in `tests/Feature/Phase10V1011RegressionTest.php`
- CI YAML valid; 5 new v10.11 sub-checks
- 68 unique global Pest helpers, 0 duplicates
- All v10.1-v10.10 preservation markers intact (11/11)
- All PHP brace balances ✓

## Manual runtime results

I cannot execute `npm run build` / `php artisan test` / browser tests in this sandbox. The dev's §6 walkthrough is the gating step before this is called fixed:

1. `php artisan optimize:clear`
2. `php artisan route:list --path=vendor/orders` → ✓ routes present
3. `php artisan route:list --path=admin/reports` → ✓ routes present
4. `php artisan test` → expect all Phase10V1011 scenarios to pass
5. `npm run typecheck && npm run build` → expect clean (Show.tsx adds 1 new prop, no other React changes)
6. Restart `php artisan serve` from the active folder
7. Hard-refresh browser
8. Click through the 4 defect confirmations in PHASE_10_v10.11_DEVELOPER_CHECKLIST.md

## Files changed

| File | Reason |
|---|---|
| `app/Domain/Reports/ReportsService.php` | §5: `amount_minor` → `requested_amount_minor` (2 query sites) |
| `app/Http/Controllers/Vendor/VendorOrderController.php` | §3: NEW `computeStatusOptions()` private method; `status_options` prop added to show() response |
| `resources/js/Pages/Vendor/Orders/Show.tsx` | §3: prop interface adds `status_options`; broken client-side availability rules replaced with server prop reads |
| `app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php` | §4: 4 action callbacks (reply/changeStatus/changePriority/assign) explicitly `$record->load(['messages.user:id,name,email'])` after mutation |
| `app/Http/Controllers/SupportTicketController.php` | §4: customer reply explicit redirect to show URL |
| `app/Http/Controllers/Vendor/VendorSupportTicketController.php` | §4: vendor reply explicit redirect |
| `app/Http/Middleware/HandleInertiaRequests.php` | §2: `getAllPermissions()->pluck()` removed from default share |
| `tests/Feature/Phase10V1011RegressionTest.php` | NEW — 17 Pest scenarios |
| `.github/workflows/ci.yml` | 5 new v10.11 sub-checks + verdict bump |
| `VERSION` | `Phase 10 v10.10` → `Phase 10 v10.11` |

## Files PRESERVED from prior releases (no regression)

All v10.1-v10.10 fix code intact:
- v10.10 direct guard in ReportsController + diagnostic + repair commands + EnsureAdminReportsAccessSeeder
- v10.9 Gate::before super_admin shortcut, `viewReports` Gate, self-healing migration
- v10.8 PricingService delegated to from CartController + CheckoutService
- v10.7 VendorFileResolver, v10.6 vendors disk, v10.5 SharedProps, v10.3 Product::fill, etc.

## Per dev §6 acceptance

**Phase 10 v10.11 is implemented but requires developer runtime verification in the running application.**

`PHASE_10_v10.11_DEVELOPER_CHECKLIST.md` lists the exact commands and confirmations.
