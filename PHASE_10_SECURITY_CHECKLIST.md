# Phase 10 — Security Checklist

This checklist covers every Phase 10 §8 security area. Each item is marked with its current state (✓ verified, ⚠ developer responsibility, ◯ documented limitation).

---

## 1. Authentication + Authorization

| Area | State | Evidence / instruction |
|---|---|---|
| Authentication | ✓ verified | Laravel Breeze + Spatie HasRoles. Sessions are encrypted; passwords are bcrypt-hashed. |
| Authorization policies | ✓ verified | `ProductPolicy`, `OrderPolicy`, `VendorPolicy`, `SupportTicketPolicy`, plus Gates: `viewReports` (Phase 10). Every controller method that mutates state calls `$this->authorize(...)`. The v9.4 Phase 9 scanner enforces this. |
| Admin permissions | ✓ verified | Filament panel: `canAccess()` on each Resource checks `hasAnyRole(['super_admin', 'admin_staff'])`. Admin Inertia routes (Phase 10 `/admin/reports`) gated by `viewReports` Gate. |
| Vendor ownership/scoping | ✓ verified | Every vendor controller resolves the vendor from request attributes (set by `vendor:approved` middleware) — never from a request param. v9.5 Pest scenario asserts cross-vendor leak prevention. Phase 10 `VendorReportsController` follows the same pattern. |
| Customer ownership/scoping | ✓ verified | `OrderController::show` checks `$order->user_id === auth()->id()` (or admin role). Tickets, bookings, wishlist, addresses all follow the same pattern. |

## 2. Input Hygiene

| Area | State | Evidence |
|---|---|---|
| Mass-assignment protection | ✓ verified | Every Eloquent model declares `$fillable` (no `$guarded = []` shortcut). The Phase 10 reports controllers pass only validated date filter params to the service. |
| CSRF protection | ✓ verified | `VerifyCsrfToken` middleware is in the `web` group by default. Inertia automatically attaches the token. The v9.4 lesson — DemoSeederTest mutating env → re-enabling CSRF → 419 — is now guarded by a CI sub-check. |
| Form validation | ✓ verified | Every controller method that accepts user input uses `$request->validate([...])` with explicit rules. File uploads validate MIME + extension + size before any storage operation. |
| SQL injection | ✓ verified | All queries go through Eloquent / query builder. The one `whereRaw('LOWER(name) LIKE ?')` in CatalogController binds the user input as a parameter, not concatenated. |
| XSS | ✓ verified | Inertia React escapes by default. Blade templates use `{{ ... }}` (escaped). The only `{!! ... !!}` raw-output usage in the codebase is in `mail/*.blade.php` templates and they emit pre-sanitized content. |

## 3. Upload Security (§7)

| Area | State | Evidence |
|---|---|---|
| MIME validation | ✓ verified | Every file-uploading controller validates `mimes:jpg,jpeg,png,webp,pdf,...`. Customization uploads have a separate allow-list. |
| Extension validation | ✓ verified | Combined with `mimes:` rule. |
| File-size limits | ✓ verified | `max:5120` (5 MB) on image uploads, `max:10240` (10 MB) on PDFs/proof files. |
| Safe file naming | ✓ verified | Uploads use `Storage::putFile(...)` which generates hashed filenames; the original filename is stored separately as metadata. |
| Executable-file rejection | ✓ verified | `mimes:` rule restricts to specific allow-listed types. Server-side validation, not just extension matching. |
| Private storage for sensitive files | ✓ verified | Customization uploads + proof files use disk `local` (not `public`). Access goes through a signed URL controller that re-verifies the requesting user owns the order/customization. |
| Public storage for genuinely-public files | ✓ verified | Product images use disk `public` and are linked via `php artisan storage:link`. |
| Storage link present | ⚠ developer task | The deploy guide includes the `php artisan storage:link` step. |

## 4. Rate Limiting

| Area | State | Evidence |
|---|---|---|
| Login throttle | ✓ verified | Laravel Breeze ships with `throttle:6,1` on `/login` POST. |
| API tokens | ◯ N/A | No public API endpoints in this codebase. |
| Heavy queries | ⚠ developer task | Phase 10 reports query `orders` + `order_items` at scale. The chunked streaming export avoids OOM. For very high traffic, consider Laravel Octane + Redis-backed rate limit on `/admin/reports/export.csv`. |

## 5. Session + Cookie Security

| Area | State | Evidence / instruction |
|---|---|---|
| Encrypted session | ✓ verified | `config/session.php` `encrypt => true`. |
| Same-site cookie | ⚠ developer task | Default is `lax`. For production set `SESSION_SAME_SITE=strict` if no cross-site embedding is intended. |
| Secure cookies | ⚠ developer task | `SESSION_SECURE_COOKIE=true` in production `.env` (deploy guide includes this). |
| HTTPS-only | ✓ enforced in code | `AppServiceProvider::boot` calls `URL::forceScheme('https')` when `app()->isProduction()` is true. |

## 6. Secrets + Configuration

| Area | State | Evidence |
|---|---|---|
| No secrets committed | ✓ verified | `.env.example` ships with empty/placeholder values. No real keys in the repo. |
| APP_KEY | ⚠ developer task | Run `php artisan key:generate` once in production; rotate only with the documented rotation procedure (would invalidate all sessions). |
| Debug mode | ⚠ developer task | `APP_DEBUG=false` in production `.env` (deploy guide). |
| Stack traces | ⚠ developer task | When `APP_DEBUG=false`, Laravel automatically returns 500 errors without stack traces. |

## 7. Phase 10 §8 — special attention items

| Area | State | Evidence |
|---|---|---|
| **Vendor package limits** | ✓ verified | `VendorProductController::store` returns 403 when over `max_products`; not just hidden. |
| **Vendor package commission** | ✓ verified | `CheckoutService::placeOrder` uses the rule → package → fallback chain. Phase 9 v9.5 CI invariant + Phase 10 reconciliation CI both run. |
| **Product update/delete restrictions** | ✓ verified | `ProductPolicy` state-restricts vendor mutation. |
| **Cart-item vendor ownership** | ✓ verified | Server-derived from product. v9.5 spoof-rejection Pest scenario. |
| **Coupon calculations** | ✓ verified | `CouponService::evaluate` + `CheckoutService::allocateCouponDiscount` enforce v9.3 invariant: sum of per-item allocation = coupon discount. CI sub-check re-asserts at runtime. |
| **Vendor earnings** | ✓ verified | Computed at order placement: `(line_total − allocation) × earning_pct ÷ 100`. Phase 10 CI runs the financial reconciliation against every non-cancelled order in the seed. |
| **Payout access** | ✓ verified | `VendorWalletController` requires `vendor:approved` middleware + checks the request attribute `vendor` (not param). Vendor cannot see another vendor's payouts. |
| **Order status actions** | ✓ verified | `OrderLifecycleService` gates each transition: only the order's vendor can `markShipped`; only the admin can refund; etc. |
| **Booking ownership** | ✓ verified | `BookingController::show` checks `$booking->user_id === auth()->id()` or admin role. Vendor sees their bookings only. |
| **Support-ticket visibility** | ✓ verified | `SupportTicketPolicy` + `ViewSupportTicket::resolveRecord` (Phase 9 v9.3) eager-load and restrict to the ticket creator + assigned admin + the vendor when ticket is vendor-routed. |
| **Review eligibility** | ✓ verified | `ReviewController::store` checks the user has a delivered order item for the product AND has not already reviewed it. Duplicate reviews blocked. |
| **Supplier credentials** | ✓ verified | `SupplierAccount::$casts` encrypts the API key. Never returned in JSON responses. |
| **Customization files** | ✓ verified | Private storage + signed-URL access. Customer can see their own; vendor sees orders assigned to them; admin sees all. |

## 8. Production deployment

| Area | Reference |
|---|---|
| `APP_ENV=production` | `PHASE_10_DEPLOYMENT_GUIDE.md` |
| `APP_DEBUG=false` | `PHASE_10_DEPLOYMENT_GUIDE.md` |
| Secure cookies | `PHASE_10_DEPLOYMENT_GUIDE.md` |
| Storage link | `PHASE_10_DEPLOYMENT_GUIDE.md` |
| Sitemap accessible | `/sitemap.xml` (Phase 10) |
| Robots.txt accessible | `/robots.txt` (Phase 10) |
| Queue worker | `PHASE_10_DEPLOYMENT_GUIDE.md` |
| Scheduler cron | `PHASE_10_DEPLOYMENT_GUIDE.md` |

## 9. Logging hygiene

| Don't log | Mechanism |
|---|---|
| Passwords | Laravel masks these by default in logs |
| Full payment details | The codebase doesn't capture full card data — payments are mocked or use external processor (Phase 5 design) |
| API secrets | `SupplierAccount` casts encrypt the key; never logged |
| Sensitive personal data | The audit log captures field changes but masks `password`, `remember_token`, and `api_key` via the `LogsActivity` configuration |

---

## Summary

**No known security defects ship with Phase 10.** Every §8 item is either verified in code or marked as a deployment-time responsibility (with a reference to the deployment guide).

The single most important runtime security guarantee is enforced at three layers:
1. **Static CI check** — `VendorReportsController` doesn't read `vendor_id` from the request.
2. **Pest scenario** — vendor A's report excludes vendor B's data.
3. **Service-level scoping** — every `ReportsService::vendor*` method filters `where('order_items.vendor_id', $vendorId)`.

Triple defense is the standard. If any layer fails, the other two catch the regression.
