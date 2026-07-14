# Phase 12.2 — Route Authorization Checklist

Audit of routes and their authorization gates. Grounded in real `routes/web.php` + `routes/api.php` middleware groups.

## Route count audit (verified via grep)

| HTTP method | Count in web.php + api.php |
| --- | --: |
| `Route::get(` | 29 |
| `Route::post(` | 28 |
| `Route::put(` | 0 |
| `Route::patch(` | 1 |
| `Route::delete(` | 11 |
| **Total (excluding grouped)** | 69 |

Individual counts are lower-bound estimates because routes inside `Route::resource()` and `Route::apiResource()` aren't counted line-by-line. Run `php artisan route:list --json | jq 'length'` on the server for the authoritative total.

## Middleware groups (verified)

| Group pattern | Count in web.php |
| --- | --: |
| `Route::middleware('guest')` | 1 |
| `Route::middleware('auth')` | 10 |
| `Route::middleware(['auth', 'vendor'])` | (multiple) |
| `Route::middleware(['auth', 'vendor:approved'])` | 5 |
| `Route::middleware(['auth', 'role:vendor'])` | (multiple) |

## Middleware aliases (verified from `bootstrap/app.php`)

- `role` → `Spatie\Permission\Middleware\RoleMiddleware::class`
- `permission` → `Spatie\Permission\Middleware\PermissionMiddleware::class`
- `role_or_permission` → `Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class`
- `vendor` → `\App\Http\Middleware\EnsureVendor::class`

## Authorization matrix (must all hold)

Test each row with a curl or browser session and verify the expected outcome.

### Public routes (no auth required)

| Route | Guest | Customer | Vendor | Admin | Expected |
| --- | :---: | :---: | :---: | :---: | --- |
| `GET /` | ✅ | ✅ | ✅ | ✅ | 200 for all |
| `GET /products` | ✅ | ✅ | ✅ | ✅ | 200 for all |
| `GET /products/{slug}` | ✅ | ✅ | ✅ | ✅ | 200 for all |
| `GET /search` | ✅ | ✅ | ✅ | ✅ | 200 for all |
| `GET /vendors/{slug}` | ✅ | ✅ | ✅ | ✅ | 200 for all |
| `GET /services` | ✅ | ✅ | ✅ | ✅ | 200 for all |
| `GET /login` | ✅ | ↩️ | ↩️ | ↩️ | Guest sees form; authed → redirect to home |
| `GET /register` | ✅ | ↩️ | ↩️ | ↩️ | Guest sees form; authed → redirect to home |
| `GET /robots.txt` | ✅ | ✅ | ✅ | ✅ | 200 |
| `GET /sitemap.xml` | ✅ | ✅ | ✅ | ✅ | 200 (if controller route registered) |
| `GET /up` (health) | ✅ | ✅ | ✅ | ✅ | 200 |

### Customer routes (`auth` middleware)

| Route | Guest | Customer | Vendor | Admin | Expected |
| --- | :---: | :---: | :---: | :---: | --- |
| `GET /orders` | ↩️ | ✅ | ✅ | ✅ | Guest → login; authed → own orders only |
| `GET /orders/{id}` | ↩️ | ✅* | ✅* | ✅ | *Only if the order belongs to them (policy check) |
| `GET /cart` | ↩️ | ✅ | ✅ | ✅ | Guest → login; authed → own cart |
| `GET /checkout` | ↩️ | ✅ | ✅ | ✅ | Guest → login; authed → checkout (guest checkout disabled) |
| `GET /wishlist` | ↩️ | ✅ | ✅ | ✅ | Guest → login; authed → own wishlist |
| `GET /bookings` | ↩️ | ✅ | ✅ | ✅ | Guest → login; authed → own bookings |
| `GET /support` | ↩️ | ✅ | ✅ | ✅ | Guest → login; authed → own tickets |

### Vendor routes (`auth` + `vendor:approved`)

| Route | Guest | Customer | Pending Vendor | Approved Vendor | Admin | Expected |
| --- | :---: | :---: | :---: | :---: | :---: | --- |
| `GET /vendor` (dashboard) | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Approved vendor only; others 403 or redirect |
| `GET /vendor/products` | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Same |
| `POST /vendor/products` | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Same |
| `GET /vendor/orders` | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Same, own order_items only |
| `GET /vendor/reports` | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Same |
| `GET /vendor/intelligence` | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Same (Phase 11B.4.2 fix — pending/suspended blocked) |
| `POST /vendor/intelligence/dismiss` | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Same |
| `GET /vendor/settings` | ↩️ | ⛔ | ⛔ | ✅ | ⛔ | Same |

The Phase 11B.4.2 fix (§Defect1.1, 1.2, 1.3 in Pest suite) verifies that pending, suspended, and rejected vendors are blocked from `/vendor/intelligence` specifically. Same guards apply to all `vendor:approved` routes.

### Admin routes (Filament, `role:super_admin` or `role:admin_staff`)

| Route | Guest | Customer | Vendor | Admin Staff | Super Admin | Expected |
| --- | :---: | :---: | :---: | :---: | :---: | --- |
| `GET /admin` (Filament) | ↩️ | ⛔ | ⛔ | ✅ | ✅ | Only admin roles |
| `GET /admin/products` | ↩️ | ⛔ | ⛔ | ✅ | ✅ | |
| `GET /admin/vendors` | ↩️ | ⛔ | ⛔ | ✅ | ✅ | |
| `GET /admin/customers` | ↩️ | ⛔ | ⛔ | ✅ | ✅ | |
| `GET /admin/orders` | ↩️ | ⛔ | ⛔ | ✅ | ✅ | |
| `GET /admin/site-settings` | ↩️ | ⛔ | ⛔ | ⛔* | ✅ | Super admin only for settings changes |
| `POST /admin/site-settings/{group}` | ↩️ | ⛔ | ⛔ | ⛔ | ✅ | Same |
| `GET /admin/translations` | ↩️ | ⛔ | ⛔ | ✅ | ✅ | |
| `POST /admin/translations/{id}/approve` | ↩️ | ⛔ | ⛔ | ✅ | ✅ | Approver role |
| `GET /admin/vendor-intelligence` | ↩️ | ⛔ | ⛔ | ✅ | ✅ | Reports permission |

*Admin staff may have read-only settings depending on the granular permission grants — verify via `EnsureAdminReportsAccessSeeder`.

## Vendor data isolation checklist

Beyond middleware, controllers must scope queries to the current vendor. Manually verify each of these bypass attempts returns 403:

- [ ] Vendor A tries to GET Vendor B's product ID directly: `/vendor/products/{B's product id}/edit`
- [ ] Vendor A tries to POST update Vendor B's product: `PATCH /vendor/products/{B's product id}`
- [ ] Vendor A tries to fetch Vendor B's order items via a modified request
- [ ] Vendor A tries to fetch Vendor B's reports via URL parameter
- [ ] Vendor A tries to dismiss Vendor B's intelligence alert
- [ ] Vendor A tries to download Vendor B's uploaded document

## Direct URL access checks

Try each in incognito / logged-out mode:

- [ ] `/admin` → redirected to login
- [ ] `/vendor` → redirected to login
- [ ] `/orders` → redirected to login
- [ ] `/cart` → redirected to login OR guest cart (if guest cart persisted before auth)
- [ ] `/api/tokens/create` → 401 (Sanctum requires auth)

Try each as authed customer:

- [ ] `/admin` → 403 or redirect
- [ ] `/vendor` → 403 or redirect
- [ ] `/admin/orders/1` → 403

Try each as authed vendor:

- [ ] `/admin` → 403 or redirect
- [ ] `/admin/vendors` → 403 or redirect

## Filament panel authorization

Filament's guard is set in `app/Providers/Filament/AdminPanelProvider.php`. Users must have `role:super_admin` or `role:admin_staff` to enter the panel. Individual resources further gate per-permission.

Verify:

```bash
$ grep -rn "canAccessPanel\|canView\|auth()->user()->can" app/Filament/ 2>/dev/null | head
```

Standard Filament pattern uses `->canAccessPanel(fn (User $user) => $user->hasRole(['super_admin', 'admin_staff']))`.

## API routes (Sanctum-guarded)

`routes/api.php` uses `auth:sanctum` middleware. Any user with a valid Sanctum token can call API endpoints. Sanctum's stateful API is enabled in `bootstrap/app.php` (`$middleware->statefulApi()`).

## What the operator must run

```bash
sudo -u www-data php artisan route:list > /tmp/routes.txt
# Review each route's middleware column
# Verify admin routes have `auth,role:super_admin` or similar
# Verify vendor routes have `auth,vendor:approved`
# Verify no route is missing auth that should have it

# Automated grep for potential leaks:
grep -E "^\s*Route::(get|post|patch|delete|put)" routes/web.php | grep -v middleware | head
# Expected: only public routes
```

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| 5 `vendor:approved` groups in web.php | ✅ | `grep -c "vendor:approved" routes/web.php` returns 5 |
| Middleware alias `vendor` registered | ✅ | `bootstrap/app.php` |
| Filament panel gated | ✅ | `app/Providers/Filament/AdminPanelProvider.php` |
| Sanctum stateful API enabled | ✅ | `bootstrap/app.php` |
| Pending vendor blocked (Phase 11B.4.2) | ✅ | Phase11B42 Pest §Defect1.1 |
| Suspended vendor blocked (Phase 11B.4.2) | ✅ | Phase11B42 Pest §Defect1.2 |
| Rejected vendor blocked | ✅ | Phase11B42 Pest §Defect1.3 |
| Vendor A cannot access Vendor B's data | ⏳ | Operator manual test |
| Full `route:list` output reviewed | ⏳ | Operator runs on server |
| Direct URL bypass attempts blocked | ⏳ | Operator manual test |
