# Troubleshooting

## `php artisan test` fails with "could not find driver" (Connection: pgsql) on every test

You'll see hundreds of identical failures, all looking like:

```
QueryException
could not find driver (Connection: pgsql, SQL: select exists (select 1 from pg_class c, ...))
at vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php:67
```

If every database-touching test fails this way (typically every Feature test, all with the same error) and only the Unit tests pass, the problem is **not in the application** — it's that your machine's PHP doesn't have the PostgreSQL PDO driver installed.

`phpunit.xml` configures tests to use PostgreSQL (`DB_CONNECTION=pgsql`). PHP then asks the PDO layer for the `pgsql` driver, which lives in the `php-pgsql` package — separate from PHP itself. If that package isn't installed, PDO can't connect even if PostgreSQL is running.

### Fix on Ubuntu / Debian

```bash
# 1. Check your PHP version
php -v          # → "PHP 8.3.x" or "PHP 8.2.x" — note the major.minor

# 2. Install the matching pgsql extension
sudo apt install php8.3-pgsql      # match your version exactly

# 3. (Only if you use PHP-FPM with nginx/apache) restart it
sudo systemctl restart php8.3-fpm  # match your version

# 4. Verify
php -m | grep -i pgsql
# Should print:
#   pdo_pgsql
#   pgsql

# 5. Re-run tests
php artisan test
```

If `php -v` shows e.g. PHP 8.2, install `php8.2-pgsql` instead — the package name MUST match your PHP version exactly. Mixing versions silently does nothing.

### Fix on RHEL / Fedora / Rocky / Alma

```bash
sudo dnf install php-pgsql
sudo systemctl restart php-fpm   # if applicable
php -m | grep -i pgsql
```

### Fix on Arch

```bash
# Uncomment ;extension=pdo_pgsql and ;extension=pgsql in /etc/php/php.ini
sudo systemctl restart php-fpm   # if applicable
```

### How to confirm this is your problem (not something else)

The signature is unmistakable:

- Every Feature test fails with the **same** `could not find driver` error
- The Unit tests (which don't touch the DB) pass
- The error happens at `Connector.php:67` inside `PDO::connect(...)`
- The Postgres server itself may or may not be running locally — that's irrelevant; PHP can't even attempt to connect because the driver is missing

If a few tests fail with different errors (TypeError, missing column, etc.) mixed in, those are real bugs — debug those separately.

### Why is PostgreSQL even the test database?

The project's CI runs the test suite against Postgres because it's our production database engine. `phpunit.xml` mirrors that so tests behave identically locally and in CI. If you genuinely cannot install `php-pgsql` (eg. shared host, locked-down environment), an alternative is to override the connection just for testing by editing `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

But beware: this means your local tests no longer match CI. The Postgres-specific code (e.g. `ILIKE` in `CatalogController::index`) won't work on SQLite, so the corresponding test (`ProductCatalogTest`'s case-insensitive search) will fail. Installing `php-pgsql` is the right answer in 99% of cases.

---

# Troubleshooting

## Phase 8 v8.1 — completion pass on Phase 8.0

### "I can't find Services / My Bookings / vendor service pages — they don't appear in any menu"

**This was the Phase 8.0 bug.** v8.1 adds them to the layout nav. After pulling v8.1:

- Storefront top nav: "Services" (always visible) + "My Bookings" (when logged in)
- Vendor sidebar nav: "Services" + "Providers" + "Bookings" (when logged in as approved vendor)
- Admin Filament: "Service Bookings" under the "Services" navigation group

If you still don't see them, run `npm run build` to recompile the layout. The Pest test `Phase 8 v8.1: storefront nav contains a Services link` and CI sub-check `Phase 8 v8.1 — navigation links present` will fail loud if any required link is missing.

### "After I book, I land on a basic detail page instead of a 'thank you' page"

**Phase 8.0 redirected to `/bookings/{id}` (the manage page) after creation.** v8.1 adds `/bookings/{id}/confirmation` with a proper success banner + reference card. The CI sub-check `Phase 8 v8.1 — booking confirmation page route exists` catches any future regression.

### "Services appear on /products and customers click 'add to cart' on appointments"

**Phase 8.0 didn't filter `/products`.** v8.1 patches `CatalogController` to exclude `type=service` from index and redirect `/products/{slug}` to `/services/{slug}` for service slugs. Run the regression test: `php artisan test --filter "v8.1: /services lists only"` and `Phase 8 v8.1: /products excludes service-type products`.

### "Where do I reschedule a booking?"

v8.1 adds reschedule buttons on both customer and vendor booking detail pages:

- **Customer**: `/bookings/{id}` → "Reschedule" button (next to "Cancel"). Opens an inline date + time form.
- **Vendor**: `/vendor/bookings/{id}` → "Reschedule booking" in the action sidebar. Opens an inline date + time form.

Both submit through `ServiceBookingService::reschedule()` which holds a row-level lock on the target slot and re-checks availability under the lock. If the target slot is full, the form returns a clean validation error and the original booking is unchanged.

### "Booking creation crashes — Mailpit isn't running locally"

Phase 8 doesn't send mail at booking creation (intentionally deferred to Phase 9). If your local crash is during user registration or another flow, confirm `.env` has `MAIL_MAILER=log` (the default in `.env.example` since Phase 7 v7.5).

The Pest test `Phase 8 v8.1: booking creation succeeds with MAIL_MAILER=log driver` runs under `Mail::fake()` and asserts no mail is sent during booking creation — proves the absence of notifications is intentional, not an accidental crash.

### "I selected a date but no time slots appeared"

The 14-day calendar in `Services/Show.tsx` only shows clickable dates that have at least one available slot. If you click a date and see "No slots available on the selected date", the slot count badge was zero — which means:

- Provider has no `ServiceAvailability` row for that weekday (0=Sunday, 6=Saturday)
- Provider has a `ServiceBlockedDate` row for that date
- All slots that day are at max capacity
- All slots that day are inside the `min_lead_time_minutes` cutoff (only matters for today's date)

The seeded demo has providers working Mon–Sat 10:00–20:00 with lunch break 13:00–14:00 — **Sundays are intentionally closed**.

### Manual smoke test for v8.1 (the 18-step check)

Documented in `PHASE_8_v8.1_PATCH_NOTES.md`. If any step fails, capture the URL + error message and report; the relevant CI sub-check should also fail.

---

# Troubleshooting

## Phase 8 — Services Marketplace

### Booking errors

**"The selected slot is no longer available."**
The slot was full when the booking attempted to commit. Either:
- Another customer booked the same slot in a near-simultaneous window.
- `max_bookings_per_slot` for that weekday is 1 and somebody else is already on it.
Refresh the page; the slot calendar will re-render with current availability.

**"Selected provider does not deliver this service."**
The service's `service_provider_assignments` pivot doesn't include the selected provider. Either the customer URL was hand-edited, or the vendor un-assigned the provider after the page loaded. Reload the service page.

**"This service requires the customer address."**
The service has `location_mode = 'customer_location'` (home visit) and the booking POST didn't include a `service_address` JSON. Fill the address fields on the booking form.

**"No active service provider is available for this service."**
The service has no providers assigned, OR all assigned providers have `is_active = false`. Vendor should assign at least one active provider in `/vendor/services/{id}/edit`.

### Lazy-load errors on booking pages

If you see `Attempted to lazy load [...] on model [App\Models\ServiceBooking]`:
1. Confirm you're on the v7.6-fixed lazy-load defense. The Phase 8 CI sub-check `Phase 8 — lazy-load defense` greps all 5 sites for required eager-loads. Run `npx tsc --noEmit` locally to also catch any unused identifier first.
2. The eager-load list in each Phase 8 controller is documented in the file headers — never remove an entry from `Order::with([...])` / `ServiceBooking::with([...])` without first checking what the presenter touches.

### `migrate:fresh --seed` fails

Almost always one of three patterns:

1. **Missing `APP_KEY`** — run `php artisan key:generate` first. The Phase 7 v6.x guard catches this on seed.
2. **Duplicate SKU on re-seed** — should be impossible in Phase 8 since all 7 `updateOrCreate` calls use real unique-index keys. If it happens, run `php artisan migrate:fresh --seed` (with `migrate:fresh` not just `db:seed`) and capture the full output for inspection.
3. **NOT NULL violation on a Phase 8 column** — the ServiceBooking model safeguard catches this BEFORE the SQL round-trip and throws a `LogicException` with a clear message naming `ServiceBookingService::createBooking`. Use the service layer for any custom seed insertions.

### `npm run build` fails on Phase 8 React files

Phase 7 v7.7 lesson: TypeScript's `noUnusedLocals: true` catches unused imports. Every Phase 8 React file was verified with `tsc --noEmit -p tsconfig.verify.json` before shipping. If your local build fails:
1. Run `npm ci` to install fresh dependencies.
2. Run `npm run typecheck` directly — the error message will name the file and line.
3. Confirm you're on the Phase 8 ship by `git log --oneline | head -3`.

### Booking dashboard shows no data after seed

The demo booking is in `customer@marketplace.test`'s account, NOT yours. Log in as `customer@marketplace.test / password` to see the seeded confirmed booking.

For the vendor side, log in as `vendor@marketplace.test / password` and visit `/vendor/bookings` — the same demo booking will show there from the vendor's perspective.

### Slots calendar is empty for some days

Possible reasons:
- The provider has no `ServiceAvailability` row for that `day_of_week` (0=Sunday by Carbon convention).
- The provider has a `ServiceBlockedDate` for that exact date.
- The date is in the past or beyond `service_details.max_advance_days`.
- The date is today and all slots are inside the `min_lead_time_minutes` cutoff.
- All slots that day are already booked at max capacity.

The demo seed sets Mon-Sat 10:00-20:00, so **Sunday is intentionally closed**.

### Slot list is too long / too short

`slot_duration_minutes` on `ServiceAvailability` determines slot length. Default 30. To change for a specific weekday, the vendor visits `/vendor/providers/{id}/availability` and re-submits the day-row with the new slot size.

If a service's `duration_minutes` is LONGER than the availability's `slot_duration_minutes`, the service code (`ServiceAvailabilityService::slotsFor`) automatically uses the service duration as the slot step to prevent overlapping bookings.

---

# Troubleshooting — Assets Not Loading

> **First-time setup? Read this before reporting any seeder crash.**
>
> ## `Symfony\Component\Mailer\Exception\TransportException — getaddrinfo for mailpit failed` during registration
>
> **Symptom (Phase 7 v7.0–v7.4 default; fixed in v7.5):**
>
> ```
> Symfony\Component\Mailer\Exception\TransportException
> Connection could not be established with host "mailpit:1025":
> getaddrinfo for mailpit failed: No such host is known
> ```
>
> The user account WAS created (check `users` table), but the verification email failed because `mailpit` only resolves inside the Docker Compose network.
>
> **One-line workaround (works on any v7.x):**
>
> ```bash
> sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' .env && php artisan optimize:clear
> ```
>
> Verification emails will land in `storage/logs/laravel.log` (search for the signed URL — paste it into your browser to verify the account).
>
> **Proper fix (v7.5):** pull v7.5 — `.env.example` now defaults to `MAIL_MAILER=log`, AND `User::sendEmailVerificationNotification()` is wrapped in `try/catch` so even a misconfigured mailer won't crash registration. The failure is logged at WARNING level (visible to Sentry / log aggregation) but the customer sees a normal "welcome" flash.
>
> **Quick check you're really on v7.5:** `grep '^MAIL_MAILER=' .env.example` should print `MAIL_MAILER=log`. And in `php artisan tinker`:
>
> ```php
> $u = \App\Models\User::factory()->create();
> $u->sendEmailVerificationNotification();   // no exception even with bad MAIL_HOST
> ```
>
> Should return cleanly even with an unreachable mailer.
>
> **If you want to use Mailpit for local development** (so you can see emails in a browser UI), start the Docker stack with `docker compose up -d mailpit` and switch your `.env` to:
>
> ```
> MAIL_MAILER=smtp
> MAIL_HOST=mailpit
> MAIL_PORT=1025
> ```
>
> Mailpit UI is at http://localhost:8025.
>
> ## `SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'file_path' cannot be null` during `php artisan migrate:fresh --seed`
>
> **If you're on Phase 7 v7.4 or later, you should never see this error.** The `CustomizationProof` model now has a `creating` event that throws a `LogicException` with a clear message *before* the SQL constraint can fire. If you see this SQLSTATE error, you're on v7.0–v7.3 — pull v7.4 and re-run.
>
> **Quick check to confirm you're on v7.4** — paste into `php artisan tinker`:
>
> ```php
> \App\Models\CustomizationProof::create(['file_path' => null]);
> ```
>
> - **v7.4 response**: `LogicException: CustomizationProof::file_path cannot be null or empty…`
> - **v7.0–v7.3 response**: `SQLSTATE[23000] Column 'file_path' cannot be null` (you're on an old archive)
>
> **Cause history (v7.0 → v7.4):**
>
> 1. **v7.0–v7.2** seeded the demo proof with `file_path = null` because I assumed the column was nullable. The migration declares it NOT NULL.
> 2. **v7.3** added `try/catch` around `Storage::put` and skipped the proof row if the write threw. But `Storage::put` returns `bool` on failure (does not throw) so the catch never fired and the seeder still inserted with a path to a non-existent file. The SQL constraint still violated because the file_path was *technically* null in code paths where `$proofPath` got reassigned.
> 3. **v7.4** added a model-level `creating` event that throws `LogicException` if `file_path` is empty, AND fixed the seeder to check `Storage::put`'s return value and verify the file exists on disk before inserting.
>
> The Phase 7 v7.4 CI sub-check `Phase 7 v7.4 — model-level safeguard against null file_path` greps the model file on every push to confirm the safeguard hasn't been removed.
>
> ## `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry` during `php artisan migrate:fresh --seed`
>
> **Symptom (Phase 7 v7.1 → v7.2 fix):** running `php artisan migrate:fresh --seed` halts partway with something like:
>
> ```
> SQLSTATE[23000]: Integrity constraint violation: 1062
> Duplicate entry '1-DEMO-TSHIRT-001' for key 'products_vendor_id_sku_unique'
> ```
>
> **Cause (the specific v7.1 bug):** the Phase 7 customizable T-shirt demo product used `sku = 'DEMO-TSHIRT-001'`, which collided with an existing Phase 3 demo product (regular Cotton T-Shirt) attached to the same vendor. The `(vendor_id, sku)` unique index rejected the second insert. v7.2 renamed Phase 7 SKUs to `DEMO-CUSTOM-MUG-001` / `DEMO-CUSTOM-TSHIRT-001` and switched to `updateOrCreate` keyed on `(vendor_id, sku)`. If you see this exact error, you're on an old v7.0 / v7.1 archive — pull v7.2 and re-run.
>
> **Cause (general):** any seeder using `firstOrCreate(['x' => 'v'], [...])` or `Model::create([...])` where the create payload contains values that violate a unique constraint, and the lookup key doesn't match the unique index. Two patterns to avoid:
>
> 1. **Lookup key ≠ unique index.** `firstOrCreate(['slug' => 'x'], [..., 'sku' => 'Y', ...])` looks up by slug, but the table's unique index is `(vendor_id, sku)`. If the slug isn't yet in the DB, Eloquent INSERTs — and the INSERT then trips a different unique constraint. Use `updateOrCreate(['vendor_id' => ..., 'sku' => ...])` keyed on the actual unique index you care about.
> 2. **Hardcoded value already used elsewhere.** Two demo seeders both using `'DEMO-XXX-001'` for the same vendor. Always make demo SKUs unique within the vendor — `DEMO-{PHASE}-{CATEGORY}-{NUMBER}` (e.g. `DEMO-CUSTOM-MUG-001`) avoids cross-phase collision.
>
> The Phase 7 v7.2 CI sub-check `Phase 7 v7.2 — unique-index lookup pre-flight` catches the first pattern statically. The `Phase 7 v7.2 — migrate:fresh --seed runs cleanly TWICE in a row` step catches the second by failing loud if any duplicate-key error appears.
>
> ## `SQLSTATE[42S22]: Column not found` during `php artisan migrate:fresh --seed`
>
> **Symptom (Phase 7 v7.0 → v7.1 fix):** running `php artisan migrate:fresh --seed` halts partway with something like:
>
> ```
> SQLSTATE[42S22]: Column not found: 1054
> Unknown column 'fulfillment_type' in 'INSERT INTO'
> ```
>
> **Cause (the specific v7.0 bug):** the Phase 7 demo seeder tried to insert `fulfillment_type` into the `products` table, but the actual column added by Phase 6 is **`fulfillment_mode`**. v7.1 fixes the seeder. If you see this exact error, you're on an old v7.0 archive — pull v7.1 and re-run.
>
> **Cause (general):** any seed / factory / service is writing to a column that doesn't exist in the migrations. The Phase 7 v7.1 CI pre-flight catches this class of bug statically — if CI passed, this shouldn't happen on master. If it does happen on a feature branch, search the seed/factory/service for the offending column name and compare against:
>
> - `app/Models/{Model}.php` — `$fillable` should NOT list a column that's not in any migration's `up()` method
> - `database/migrations/*` — search for `Schema::table('table_name'` to find every column added to a given table
>
> The Phase 7 v7.1 CI sub-check `Phase 7 v7.1 — schema-vs-code pre-flight` runs a Python static analyser that catches every case in Phase 7 PHP write paths. Extending it to your new phase is one of the safest investments you can make.
>
> ## Phase 7 — Customization upload / storage troubleshooting
>
> ### "The file failed to upload" or 422 on `/cart/items/customized`
>
> Most likely cause: file too large or wrong type. Phase 7 validates:
> - **Size**: per-field `max_file_size_kb` (default 5120 KB / 5 MB if unset). Form-level cap: 10240 KB on proof uploads.
> - **Extension**: must be in the field's `allowed_file_types` array.
> - **MIME**: file's actual MIME type must match the allowed extensions. A renamed `.exe → .jpg` is rejected.
>
> If the customer sees a 419 "Page expired" instead, their session cookie expired during a slow upload. Just have them log in again and retry.
>
> ### Downloaded customization file returns 404
>
> The demo seed creates `OrderItemCustomization` rows WITHOUT an actual file on disk (`file_path = null`). The download link returns 404 — this is correct. A real customer upload via `/cart/items/customized` saves an actual file.
>
> If a real upload returns 404, check:
> - `storage/app/private/customizations/{user_id}/` exists and contains the file
> - the `local` disk's `root` in `config/filesystems.php` points at `storage_path('app/private')`
> - PHP has write permission on `storage/app/private/`
>
> ### Customer can see another customer's file
>
> **This should never happen** — `CustomizationFileController::show` walks `Order::where('user_id', auth()->id())->findOrFail($orderId)` first. If you've found a way to bypass this, file a security report immediately.
>
> ### `/storage/customizations/...` returns a file (not 404)
>
> **This is a misconfiguration that breaks Phase 7's security guarantee.** The `local` disk should point at `storage/app/private` (NOT `storage/app/public`) and there should be NO symlink from `public/storage` to anything under `private/`. CI sub-check 4 verifies this returns 404 — if it doesn't, do not ship.
>
> ### Vendor can't upload a proof
>
> Check:
> - The vendor has the `customization_proofs.upload` permission (assigned by `RolesAndPermissionsSeeder` to the `vendor` role).
> - The order_item being uploaded against has `vendor_id = $vendor->id`. Cross-vendor uploads return 404.
> - The file is one of: `image/jpeg`, `image/png`, `image/webp`, `application/pdf`, max 10240 KB.
>
> ### "This proof cannot be sent in its current state"
>
> Only `draft` and `rejected` proofs can be (re-)sent. An already-sent or already-approved proof is final — upload a new proof instead.
>
> ### Customization fields don't appear on the product detail page
>
> Check:
> - Product type is `custom` (not `simple`, `dropship`, etc.).
> - At least one field exists with `is_active = true`.
> - The CatalogController eager-loads `activeCustomizationFields` (it should, as of v8.0).
>
> ---
>
> ## `npm run build` fails with `error TS2344: Type 'X' does not satisfy the constraint 'PageProps'`
>
> **Symptom:** `npm run build` (or `npm run typecheck`) shows:
>
> ```
> error TS2344: Type 'PageProps' does not satisfy the constraint 'PageProps'.
> Type 'PageProps' is missing the following properties from type 'PageProps':
>   app, marketplace, auth, translations, cart_summary
> ```
>
> **Cause:** the page declared a local `interface PageProps { ... }` (or `type FlashProps = ...`) and passed it as the generic to `usePage<X>()`. Inertia v2 augments `@inertiajs/core`'s `PageProps` (in `resources/js/types/inertia.d.ts`) to extend `SharedProps` which requires `app`, `marketplace`, `auth`, `translations`, `cart_summary`, `flash`. Any custom type passed to `usePage<X>()` must include those fields.
>
> **Fix:** import `SharedProps` from `@/types/inertia` and use it directly (or extend it):
>
> ```ts
> import type { SharedProps } from '@/types/inertia';
> import { usePage } from '@inertiajs/react';
>
> // CASE A — page only needs SharedProps fields (auth, flash, app, etc.)
> const { props } = usePage<SharedProps>();
>
> // CASE B — page also has its own Inertia props from the controller
> type MyPageProps = SharedProps & { items: Item[]; pagination: { ... } };
> const { props } = usePage<MyPageProps>();
> ```
>
> **Do NOT** declare a local `interface PageProps` or `type FlashProps` — even if you're not currently passing it to `usePage<>`, the name shadows the augmented global type and is a future-bug magnet. Use page-specific names: `SupplierProductsPageProps`, `CheckoutPageProps`, etc.
>
> Phase 6 v7.3+ adds a CI sub-check that fails the frontend job loudly if any `resources/js/Pages/Vendor/Supplier/**/*.tsx` violates this rule.
>
> ---
>
> ## `MissingAppKeyException` or "No application encryption key has been specified"
>
> **Symptom:** running `php artisan migrate:fresh --seed` on a fresh clone halts partway through with `Illuminate\Encryption\MissingAppKeyException`.
>
> **Cause:** Phase 6 stores supplier integration credentials encrypted at rest (`SupplierIntegration::credentials` is cast as `encrypted:array`). Laravel's Encrypter requires `APP_KEY` to be set; without it, the seeder cannot write the demo supplier integration.
>
> **Fix (one guided command — Phase 6 v7.2+):**
>
> ```bash
> php artisan marketplace:setup-demo
> ```
>
> The command checks `.env`, generates `APP_KEY` if missing, runs `optimize:clear`, runs `migrate:fresh --seed`, and prints demo logins. Add `--force` to skip confirmations.
>
> **Manual fix (4 commands in order):**
>
> ```bash
> cp .env.example .env
> php artisan key:generate
> php artisan optimize:clear
> php artisan migrate:fresh --seed
> ```
>
> Note: `php artisan optimize:clear` is important if you've previously run a half-broken `migrate:fresh --seed` that left stale cached config — without it the seeder can re-read the old (empty) `APP_KEY` value from cache even after you've generated a new one.
>
> Phase 6 v7.1+ traps this case proactively — `DemoSeeder::run()` throws a clear `RuntimeException` with the exact remedy before any encrypted write is attempted.
>
> ## "The `--seed.` option does not exist"
>
> **Cause:** you typed a trailing dot on the command (`php artisan migrate:fresh --seed.`). Laravel rejects unknown options.
>
> **Fix:** use the exact command — `php artisan migrate:fresh --seed` (no dot, no period at the end). Or simply use the guided command `php artisan marketplace:setup-demo` which never lets this typo happen.

---

If the admin panel (or any page) looks like raw HTML — plain inputs, blue underlined links, oversized icons, no layout — your CSS/JS isn't reaching the browser. Walk this list **top to bottom**.

---

## 1. Quick triage in the browser

Open the page that looks broken → press **F12** → **Network** tab → **reload**.

Look at the responses for `.css`, `.js`, and the manifest. Decide which case you're in:

| What you see in DevTools | Means | Jump to |
|---|---|---|
| Many **404** responses for `/css/filament/*.css` or `/js/filament/*.js` | Filament's assets aren't published into `public/` | [§2](#2-filament-assets-missing-most-likely) |
| **404** on `/build/manifest.json` or `/build/assets/*.js` | Vite never built, or built output is in the wrong place | [§3](#3-vite-build-missing) |
| Assets load with **200 OK** but page is still unstyled | The HTML never asked for them (Blade @vite directive broken) | [§4](#4-blade-template-isnt-emitting-vite-tags) |
| Assets request goes to `localhost:5173` and fails | You're in dev mode but Vite dev server isn't running | [§5](#5-vite-dev-server-not-running) |
| **403** on asset URLs | Permission / nginx config | [§6](#6-403-forbidden) |
| **500** on asset URLs | Almost never an asset problem — PHP error in middleware. Check `storage/logs/laravel.log` | — |
| Console: "Mixed Content: blocked" | Site is HTTPS but assets are HTTP (or vice-versa) | [§7](#7-mixed-content-httphttps) |
| Console: "Failed to load module script: Expected JS, got HTML" | Vite returned the Laravel 404 page instead of a JS file | [§3](#3-vite-build-missing) |

---

## 2. Filament assets missing (most likely)

**Symptom:** `/css/filament/forms.css`, `/js/filament/filament/app.js` etc. all 404 in DevTools. Admin UI is unstyled.

**Cause:** Filament v3's CSS and JS live inside `vendor/filament/filament/`. They have to be **copied to `public/`** by a Composer post-install hook. If that hook is missing (which it was in v3.0), nothing publishes them.

**Fix (one command, inside your container or Codespace):**

```bash
php artisan filament:upgrade
```

You should see output like:

```
Filament: Successfully published assets!
Livewire: Successfully published assets!
```

After this, **hard-refresh** the admin page (Cmd/Ctrl-Shift-R). The Filament UI should now render correctly.

**Verify what got published:**

```bash
ls -la public/css/filament/    # should show several .css files
ls -la public/js/filament/     # should show several .js files
```

**The permanent fix is already in v3.1's `composer.json`** — every future `composer install` runs `filament:upgrade` automatically. Run `composer install` once and you'll never have to think about this again.

---

## 3. Vite build missing

**Symptom:** `/build/manifest.json` 404s, or the page's `<head>` has no `<link rel="stylesheet">` / `<script type="module">` tags pointing at `/build/`.

**In production** (Docker, deployed VPS): the Docker image's Stage 1 (Node builder) runs `npm run build` and copies `public/build/` into the final image. If your image was built before v3.1, rebuild it:

```bash
docker compose build --no-cache app
docker compose up -d
```

**In development** (Codespaces, local without Docker): you have two options.

**Option A — production build (one-off):**
```bash
npm install      # if you haven't yet
npm run build    # writes public/build/manifest.json + assets/*.{css,js}
```

**Option B — dev server (auto-rebuild on save):**
```bash
npm install
npm run dev      # leave this running in its own terminal
```
With `npm run dev` you don't need `public/build/` — `@vite()` rewrites itself to point at the dev server on port `5173`. **You must keep this terminal open while you work.**

**Verify the manifest exists and has the right entries:**

```bash
ls -la public/build/manifest.json
cat public/build/manifest.json | python3 -m json.tool | grep -E "app\.(css|tsx)"
```

You should see lines containing `resources/css/app.css` and `resources/js/app.tsx`.

---

## 4. Blade template isn't emitting Vite tags

**Symptom:** Assets actually exist on disk (you can `curl` them and get 200) but the rendered HTML's `<head>` doesn't reference them at all.

**View page source** (Ctrl-U) on the broken page. Look in `<head>`. You should see something like:

```html
<link rel="modulepreload" href="https://your-host/build/assets/app-Bxyzabc.js">
<link rel="stylesheet" href="https://your-host/build/assets/app-Bxyzabc.css">
```

If you see **nothing** like that, either:

1. The Blade template uses unknown directives that crash silently. v3.1 has already removed `@routes` (which required Ziggy, which we never installed) and simplified `@vite()` so this can no longer happen.
2. You're viewing a Filament admin page, which has its own `<head>` and doesn't use `app.blade.php`. Filament's `<head>` will reference `/css/filament/...` — see [§2](#2-filament-assets-missing-most-likely).

If you see Vite tags but pointing at `http://localhost:5173/...` and you're running in production, see [§5](#5-vite-dev-server-not-running).

---

## 5. Vite dev server not running

**Symptom:** Asset URLs look like `http://localhost:5173/resources/js/app.tsx` and they 404 or hang.

**Cause:** Laravel's `@vite()` detects `public/hot` and switches to dev-server mode when that file exists. The file is created when `npm run dev` starts, and deleted when it stops cleanly. If Vite crashed or you killed it with `kill -9`, the file is stale.

**Fix:**

```bash
rm -f public/hot         # remove the stale "Vite is running" marker
npm run build            # OR `npm run dev` if you want hot reload
```

---

## 6. 403 Forbidden

**Symptom:** `403` on asset URLs.

Almost always one of:

1. **Web root pointed at the wrong directory.** Your web server must serve from `/path/to/marketplace/public/`, not from the project root. Check nginx/Apache config.

   ```bash
   # Inside the container, confirm nginx is pointed at public/
   grep 'root ' /etc/nginx/http.d/default.conf
   # Should show:  root /var/www/html/public;
   ```

2. **File permissions.** The web server user (`www-data`) must be able to read `public/build/`, `public/css/filament/`, `public/js/filament/`.

   ```bash
   chmod -R a+r public/build public/css public/js
   ```

3. **You used the wrong port.** Your app is on `http://localhost:8000` (mapped from container port 80), not on `:80` or `:5173`. Visit `http://localhost:8000/admin`.

---

## 7. Mixed content (HTTP/HTTPS)

**Symptom:** Browser console says "Blocked loading mixed active content".

**Cause:** Your site is on `https://` but the asset URLs Laravel generates are `http://` (or vice-versa).

**Fix in `.env`:**

```ini
APP_URL=https://your-real-domain.tld
```

For local dev:
```ini
APP_URL=http://localhost:8000
```

After editing `.env` always run:
```bash
php artisan config:clear
```

If your server sits behind a load balancer / Cloudflare that terminates TLS, you may also need to set `TrustProxies`. That's covered in Laravel docs — but for a typical local Codespaces setup this is **not** the cause; check the simpler items first.

---

## 8. Files that should exist after a successful setup

```
public/
├── build/
│   ├── manifest.json                ← Vite manifest. MUST exist.
│   └── assets/
│       ├── app-<hash>.css           ← Tailwind + your CSS
│       ├── app-<hash>.js            ← React entry
│       └── Welcome-<hash>.js        ← Code-split page chunks
├── css/
│   └── filament/
│       ├── filament/app.css         ← Filament admin styling
│       ├── forms/forms.css
│       └── …
└── js/
    └── filament/
        ├── filament/app.js          ← Filament admin JS (Alpine, Livewire)
        ├── forms/components/*.js
        └── …
```

If any of those are missing, you've identified your problem.

---

## 9. One-shot rebuild from scratch (the "nuclear" option)

When you've changed too many things and want a clean slate:

```bash
# Inside the container, from /var/www/html
rm -rf public/build public/css/filament public/js/filament public/hot
composer install                       # triggers filament:upgrade
npm install
npm run build
php artisan optimize:clear             # clears config/route/view caches
```

Then **hard-refresh** the browser (Cmd/Ctrl-Shift-R).

---

## 10. Still broken?

Run this script and paste its output. It tells you which step failed:

```bash
#!/usr/bin/env bash
set +e
echo "=== Asset health check ==="
echo
echo "1. Vite manifest:"
[ -f public/build/manifest.json ] && echo "  ✓ exists ($(wc -c < public/build/manifest.json) bytes)" || echo "  ✗ MISSING — run 'npm run build'"
echo
echo "2. Filament CSS:"
[ -d public/css/filament ] && echo "  ✓ $(find public/css/filament -type f | wc -l) files" || echo "  ✗ MISSING — run 'php artisan filament:upgrade'"
echo
echo "3. Filament JS:"
[ -d public/js/filament ] && echo "  ✓ $(find public/js/filament -type f | wc -l) files" || echo "  ✗ MISSING — run 'php artisan filament:upgrade'"
echo
echo "4. Vite hot file (only present when 'npm run dev' is running):"
[ -f public/hot ] && echo "  • present → @vite() points at the dev server" || echo "  • absent  → @vite() uses public/build/"
echo
echo "5. APP_URL from .env:"
grep "^APP_URL=" .env 2>/dev/null || echo "  ✗ APP_URL not set"
echo
echo "6. Routing check:"
php artisan route:list --path=admin 2>&1 | head -5 || echo "  ✗ artisan failed"
```

Save it as `bin/check-assets.sh`, run it, and the failing line is your next step.

---

## 11. 419 Page Expired (CSRF)

If you see **419** in the browser or in DevTools Network:

1. **Hard-refresh** the page (Cmd/Ctrl-Shift-R). The most common cause is a stale page from a previous build.
2. **Confirm the `XSRF-TOKEN` cookie is set.** DevTools → Application → Cookies → your domain. If `XSRF-TOKEN` is missing, the browser isn't receiving it from the server. Causes:
   - `APP_URL` in `.env` doesn't match the URL you're using in the browser (e.g. APP_URL is `https://…` but you're on `http://localhost`).
   - `SESSION_SECURE_COOKIE=true` while serving over HTTP — browser refuses to store a Secure cookie on non-HTTPS.
   - `SESSION_DOMAIN` set to something the browser doesn't match.
3. **Confirm the cookie is being sent back.** When you submit a form, look at the request headers in Network → Request Headers → Cookie. You should see `XSRF-TOKEN=…; laravel_session=…`. If `XSRF-TOKEN` is missing from the request, the browser isn't sending it. Same causes as #2.
4. **Confirm v3.3's `bootstrap.ts` is in place.** The file should contain `xsrfCookieName` — NOT a `<meta name="csrf-token">` lookup:
   ```bash
   grep -E "xsrfCookieName|csrf-token" resources/js/bootstrap.ts
   ```
   You should see `xsrfCookieName  = 'XSRF-TOKEN'`. If you still see `meta[name="csrf-token"]` here, you didn't get v3.3.
5. **Clear config cache after editing `.env`:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```
6. **Use the recommended `.env` defaults for local dev (from `.env.example`):**
   ```ini
   APP_URL=http://localhost:8000
   SESSION_DOMAIN=null
   SESSION_SECURE_COOKIE=false
   SESSION_SAME_SITE=lax
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000
   ```

If 419 still happens after all of the above, run `php artisan route:list | grep -i csrf` and send the output along with the failing Network tab request/response headers.

---

## 12. "Logged out unexpectedly during navigation"

Almost always the same root cause as 419: a missing/stale CSRF cookie that triggers a session reset somewhere. Walk §11 first. After that, if you're still being bounced:

1. Check that you're not running with two different `APP_URL` values across browser tabs.
2. Confirm `SESSION_DRIVER=redis` (or whatever you set) is actually reachable: `php artisan tinker --execute="echo Cache::driver('redis')->getStore()->getRedis()->ping();"` should print `PONG`.
3. If Redis is configured but down, the session can't be read or written → every request looks like a fresh visitor → you appear to be "logged out".

---

## Phase 4 — Cart / Checkout / Orders / Payments

### Payment stuck in pending after COD checkout

This is **expected** behavior. Cash on Delivery is intentionally pending until the admin marks it captured upon successful delivery.

1. Sign in at `/admin/login` as super_admin
2. Operations → Orders → filter `payment_status = pending`
3. Click the order row → "Mark COD paid" action (only visible if the order's most recent payment is COD and still pending)
4. Order moves to `payment_status = paid` and the order event log records the capture

The same applies to manual_transfer — use the "Confirm transfer" row action after verifying the customer's bank transfer landed in your account.

### Customer reports "your cart is in KWD; this product is priced in USD" error

Phase 4 carts hold **one currency at a time** — no FX conversion at cart or order time. If a customer has KWD items in their cart and tries to add a USD-priced product, the second add is rejected. Workaround: customer empties their cart and starts over in the new currency, OR they place separate orders per currency.

This is a deliberate Phase 4 design choice — multi-currency carts with FX shipped together is a Phase 5+ feature.

### "Mark items shipped" button doesn't show on vendor order detail

The button only appears when ALL of these are true:
1. Order's `payment_status` is `paid`
2. At least one of the vendor's items has `fulfillment_status = unfulfilled`
3. The current user is the approved vendor that owns those items

Common reasons it's hidden:
- Payment hasn't been captured yet (COD order awaiting admin capture). Admin captures first → vendor can then ship.
- Items have already been marked shipped (`fulfillment_status = fulfilled`).
- The current vendor doesn't own any items in this order (they shouldn't be seeing the order at all then — file a bug if they are).

### `online_mock` payments show "captured" but I want to test the failure path

Set `force_outcome = fail` on the payment_methods config:
```sql
UPDATE payment_methods SET config = '{"force_outcome":"fail"}' WHERE slug = 'online_mock';
```
The current MockOnlineProvider implementation ignores this flag (always succeeds) — but the column is reserved for it. When MyFatoorah / Tap / Stripe are wired up, their config blocks will go here.

### Order placed but no payment row created

`CheckoutService::place()` and `PaymentService::initiateFor()` run in **separate** transactions for a deliberate reason: a failure of the payment provider shouldn't undo the order. Check `payments` table for the order — if missing, the provider initiate call failed; look at `storage/logs/laravel.log` for the exception. The customer can retry the payment from `/orders/{id}` (a retry button ships in a future polish phase; for now, admin can capture/refund manually).

### Vendor sees the wrong commission percentage on their order

Commission is **snapshotted** at order placement. If the platform admin changes a `vendor_commission_rules` row AFTER an order was placed, the order's `order_items.commission_percent` does NOT update — by design (so vendors are not surprised by retroactive cuts to their settled earnings). Old orders show the rate that applied at the time; new orders show the current rate.

If the snapshot looks wrong on a *new* order, check (in order of specificity):
1. Is there a `vendor_commission_rules` row with `scope=product, scope_id=<product_id>`? Product-scoped wins everything.
2. Is there one with `scope=category, scope_id=<category_id>`?
3. Is there one with `scope=vendor, vendor_id=<vendor_id>`?
4. Otherwise the resolver falls back to `vendors.vendor_package_id`'s `default_admin_commission_percent` (30% / 20% / 10% for Basic / Standard / Professional).

### Order number format / per-year sequence
Format: `MK-YYYY-NNNNNN` (e.g. `MK-2026-000042`). The 6-digit sequence resets at each year boundary, so `MK-2027-000001` is the first order of 2027. If you need a different prefix, change the constructor default in `App\Domain\Order\OrderNumberGenerator`.

---

## Phase 4 v5.2 — Address schema

### `SQLSTATE[42S22]: Column not found: 'full_name'` on /checkout
You're running v5.0 or v5.1 — both have a phantom-column bug. Apply v5.2 and run `php artisan migrate:fresh --seed`. The `addresses` table is Gulf-style (block/street/building/floor/apartment + governorate as `state`); v5.0/v5.1 wrongly queried for `full_name`, `line1`, `line2`, `region`. See `PHASE_4_v5.2_PATCH_NOTES.md`.

### Order detail page shows empty address fields after v5.2
You upgraded the code but didn't re-migrate. The `order_addresses` table still has the old column shape from v5.0/v5.1. Fix:
```bash
docker compose exec app php artisan migrate:fresh --seed
```
Existing orders placed via the broken flow had failures saving addresses anyway — `migrate:fresh` is safe.

### Customer has no saved address — clicking Proceed to checkout still works?
Yes. v5.2's checkout page detects `has_addresses=false` and shows the inline address form by default, pre-populated with country=KW and the user's name as the recipient. The customer types their first delivery address inline.

### How to verify the schema is right after upgrading
```sql
-- expect: yes recipient_name, no full_name
SELECT column_name FROM information_schema.columns
WHERE table_name = 'order_addresses'
ORDER BY ordinal_position;
```
Or via Laravel:
```bash
docker compose exec app php artisan tinker --execute="print_r(Schema::getColumnListing('order_addresses'));"
```

---

## Phase 4 v5.3 — Controller return types + demo data

### `TypeError: Return value must be of type Symfony\Component\HttpFoundation\Response, Inertia\Response returned`
You're running v5.0–v5.2 — every version before v5.3 has this bug in `CheckoutController::show()`. Apply v5.3 and the type is fixed to a proper union (`Inertia\Response | RedirectResponse`). No `migrate:fresh` needed for this specific fix — it's a code-only change.

### After `migrate:fresh --seed` the demo vendor has no products / customer has no address
You're running v5.2 or earlier. v5.3 introduces `DemoSeeder` which fleshes out the bare role-only accounts into a complete testable environment. Apply v5.3 and re-run `php artisan migrate:fresh --seed`.

### `DemoSeeder skipped (only runs in local/development envs)`
Expected when running migrations under `APP_ENV=production` or `APP_ENV=staging`. The seeder intentionally skips outside local/development so demo accounts don't pollute real environments. To force it to run in a non-local env: temporarily set `APP_ENV=local` for the seed run, or call `php artisan db:seed --class=DemoSeeder` directly (the seeder will still check the env and bail — adjust the guard in `app/database/seeders/DemoSeeder.php` if you genuinely want demo data outside local).

### `DemoSeeder skipped under testing env`
Expected. PHPUnit / Pest tests run with `APP_ENV=testing`, and `DemoSeeder` self-guards so it doesn't surprise tests that call `$this->seed(SomeSeeder::class)`. If you need demo data inside a test, flip the env manually:
```php
app()->detectEnvironment(fn () => 'local');
$this->seed(DatabaseSeeder::class);
```
(See `DemoSeederTest.php` for the pattern.)

### How to verify the v5.3 return-type fix in production
```bash
docker compose exec app php artisan tinker --execute="
  \$method = new ReflectionMethod(\App\Http\Controllers\CheckoutController::class, 'show');
  \$rt = \$method->getReturnType();
  print_r(\$rt instanceof ReflectionUnionType ? array_map(fn(\$t) => \$t->getName(), \$rt->getTypes()) : [\$rt?->getName()]);
"
```
Expected output:
```
Array
(
    [0] => Inertia\Response
    [1] => Illuminate\Http\RedirectResponse
)
```
If you see `Symfony\Component\HttpFoundation\Response` you're still on v5.0–v5.2.

---

## Phase 4 v5.4 — Filament closures, product images, Place Order

### Admin: "[$s] was unresolvable" on Vendor Subscriptions (or any Filament page)
A closure used an untyped, unrecognized parameter name. Filament v3 resolves closure params by name (`$state`, `$record`, `$get`, …) or by type hint. Rename `$s`→`$state`, `$r`→`$record`, or add a type hint. `FilamentClosureRegressionTest` catches this across `app/Filament`. Fixed for all resources in v5.4.

### Product images don't show / broken-image icon
1. Ensure `config/filesystems.php` exists (added in v5.4) and `MEDIA_DISK` is `public` (default) or `s3`.
2. Run `php artisan storage:link` so `/storage` maps to `storage/app/public`.
3. Re-seed: `php artisan migrate:fresh --seed` — demo products get generated images.
Pre-v5.4 the frontend printed `[image: path]` as text and uploads went to the private disk; both are fixed in v5.4.

### Uploaded image returns 404
The `storage:link` symlink is missing. Run `php artisan storage:link --force`. The Docker entrypoint does this on boot; if you run outside Docker, do it manually. Verify with `ls -l public/storage` (should be a symlink to `storage/app/public`).

### Using Cloudflare R2 / MinIO for images
Set `MEDIA_DISK=s3` plus `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT=true`. The `ProductImage::url` accessor resolves through whatever `media_disk` points to.

### Place Order button does nothing
Fixed in v5.4. If you backported, ensure `Checkout/Show.tsx` uses the `useForm` `post()` helper (not bare `router.post`) and that the page renders `flash.error` + validation errors. Pre-v5.4 a failed order was silent because errors landed in the shared errors bag, not the form's.

---

## Phase 4 v5.5 — `authorize()` undefined method

### `Call to undefined method App\Http\Controllers\OrderController::authorize()`
The base `Controller` class is missing the `AuthorizesRequests` trait. Laravel 11 ships it empty by default. Fix in v5.5: add `use AuthorizesRequests;` to `app/Http/Controllers/Controller.php`. Affects every `$this->authorize(...)` call across `OrderController`, `VendorOrderController`, and `VendorProductController` (9 sites).

### Verifying the fix is applied
```bash
docker compose exec app php artisan tinker --execute="
  echo in_array('Illuminate\\\\Foundation\\\\Auth\\\\Access\\\\AuthorizesRequests',
                class_uses_recursive(\App\Http\Controllers\Controller::class))
       ? 'AuthorizesRequests OK' : 'MISSING — re-apply v5.5';
"
```

### Policy autodiscovery seems broken
Laravel 11 auto-discovers `App\Models\X` → `App\Policies\XPolicy`. If yours don't match that convention (e.g. `App\Models\Order` should map to `App\Policies\OrderPolicy`), either rename or register them explicitly via `Gate::policy(Order::class, OrderPolicy::class)` in a service provider. All policies in this codebase follow the convention and auto-discover correctly.

### Test fails: "Foreign customer should see 403, got 500"
That means `AuthorizesRequests` is still missing — the call hits `$this->authorize()` and crashes before the policy denies. Re-apply v5.5.

---

## Phase 4 v5.6 — Stability bundle troubleshooting

### `error TS5103: Invalid value for '--ignoreDeprecations'`
The flag value didn't match what your installed TypeScript expects. v5.6 removes the line. Strict mode and unused-import checks stay enabled. If you want to re-add it later for a specific TS version, valid values are `"5.0"` for TS 5.x.

### `'X' is declared but its value is never read.`
TypeScript's `noUnusedLocals` / `noUnusedParameters` rules — kept ON in v5.6. Remove the unused import. To find all instances at once: `npm run typecheck`.

### `Type 'X' does not satisfy constraint 'PageProps'.`
Inertia v2 made `usePage<T>` generic stricter: `T` must have a string-index signature. Use the project's `SharedProps` type from `@/types/inertia` — it already has both the index signature and typed `flash`. Pattern:
```tsx
import type { SharedProps } from '@/types/inertia';
const page = usePage<SharedProps>();
const flashError = page.props.flash?.error ?? null;
```

### `Attempted to lazy load [items] on model [App\Models\Order] but lazy loading is disabled.`
`AppServiceProvider` calls `Model::shouldBeStrict(! app()->isProduction())`. The fix is to eager-load whatever your view/route accesses:
```php
Order::with(['items', 'shippingAddress', 'events', 'payments'])->findOrFail($id);
```
For Filament resources, override `getEloquentQuery()`:
```php
public static function getEloquentQuery(): Builder {
    return parent::getEloquentQuery()->with(['items', 'shippingAddress', 'payments']);
}
```
**Do not** disable `shouldBeStrict()` — it's catching real N+1 problems.

### `/storage/products/.../X.png` returns 403 (forbidden)
Run these once:
```bash
php artisan storage:link
find storage/app/public -type f -exec chmod 0644 {} \;
find storage/app/public -type d -exec chmod 0755 {} \;
```
The v5.6 `config/filesystems.php` permissions block ensures future uploads get 0644 automatically, but pre-existing files from v5.0–v5.5 need the one-off chmod.

### Filament product image upload says "Forbidden"
Same cause as the `/storage/` 403 above. The upload succeeds but the preview can't read the file. Apply v5.6 + chmod the existing files. If still seeing it, check that the user is authenticated to the Filament panel — Livewire's temporary upload route requires the panel session.

### After applying v5.6 — what to run
```bash
npm install
npm run typecheck        # validates TS config + unused imports + Inertia typing
npm run build
php artisan optimize:clear
php artisan storage:link
php artisan migrate:fresh --seed   # if you want fresh demo data + images
```

---

## Phase 4 v5.6 — TypeScript, lazy loading, image visibility

### `tsconfig.json: error TS5101: Option 'baseUrl' is deprecated`
TS 5.x warns about `baseUrl`. Don't add `ignoreDeprecations: "6.0"` (TS 5.x errors that as TS5103 invalid). Either set `ignoreDeprecations: "5.0"` (valid on TS 5.x) **OR** remove `baseUrl` entirely — `paths` works standalone since TS 4.1. v5.6 takes the second approach for forward-compatibility through TS 7.

### `tsconfig.json: error TS5103: Invalid value for '--ignoreDeprecations'`
You set `"6.0"` on TS 5.x. The valid value on TS 5.x is `"5.0"`. Better: drop `baseUrl` from `compilerOptions` entirely (see above).

### `Attempted to lazy load [items] on model [App\Models\Order] but lazy loading is disabled`
Strict-mode catching a missing eager load. Triggered by Filament's default route-model binding skipping `OrderResource::getEloquentQuery()`. v5.6 fixes by overriding `resolveRecord` on `ViewOrder` + `EditOrder` and adding `$order->loadMissing(['items'])` in lifecycle service methods.

If you hit this on a different model: either eager-load in the query (`->with(['relation'])`) or call `$model->loadMissing(['relation'])` before iterating. Do NOT disable `Model::shouldBeStrict` — that protection has caught multiple bugs in this codebase.

### Product image returns 403 Forbidden at `/storage/...`
OS-level. The fix sequence:
1. `php artisan storage:link --force` (on Windows, run as Administrator)
2. Linux: `sudo chown -R www-data:www-data storage` then `sudo find storage -type f -exec chmod 644 {} \;` and `sudo find storage -type d -exec chmod 755 {} \;`
3. Verify `.env` `APP_URL` matches the URL you're hitting in the browser (localhost vs 127.0.0.1 matters)
4. If using `php artisan serve`, switch to the Docker nginx — `serve` has flaky symlink handling on some platforms

### Filament FileUpload returns forbidden during upload
Livewire's signed-URL check failed. Causes:
1. **APP_URL mismatch with request URL** — most common. Align them in `.env`.
2. **Restrictive perms on `storage/app/livewire-tmp`** — `chmod 755 storage/app && chmod 755 storage/app/livewire-tmp`
3. **php.ini**: ensure `file_uploads = On`, `upload_max_filesize ≥ 5M`, `post_max_size ≥ 6M`

### `usePage<T>() does not satisfy constraint 'PageProps'`
Inertia v2's `usePage<T extends PageProps>` requires `T` to have a string index signature. Use the project's `SharedProps` type from `@/types/inertia` (which has `[key: string]: unknown` and module-augments `PageProps`). Example:
```ts
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types/inertia';
const page = usePage<SharedProps>();
const flashError = page.props.flash?.error;
```

### Unused-import TypeScript errors
The project has `"noUnusedLocals": true`. Just remove the offending import line. Don't disable the check — it catches real bugs.

---

## Phase 4 v5.7 — multi-product checkout

### `Attempted to lazy load [vendor] on model [App\Models\Product] but lazy loading is disabled` during checkout
Strict-mode N+1 detector caught a missing eager load. Specific to multi-item carts because the detector requires ≥2 parents in a collection to fire — single-item carts silently lazy-load.

v5.7 fix sites:
- `CheckoutService::place()` — `loadMissing(['items.product.vendor.activeSubscription.package', 'items.product.category', 'items.variant'])`
- `CartController::index()` + `CheckoutController::show()` — `$cart->load(['items.product.primaryImage', 'items.vendor', 'items.variant'])`
- `OrderLifecycleService::cancel()` — `loadMissing(['items.variant', 'items.product'])`

If you hit a similar error on a different relation: trace from the iteration point upward to the query, and add the missing relation to `with()`/`loadMissing()`. Do NOT disable strict mode — multiple bugs in this codebase have been caught by it.

### Want to test multi-vendor checkout manually?
After `php artisan migrate:fresh --seed`, sign in as `customer@marketplace.test`. Add 1 product from Demo Trading Co. + the Handwoven Beach Towel from Coastal Goods (`/vendors/coastal-goods`). Checkout → single order with 2 line items, different `vendor_id` per line. Each vendor can sign in and will see only their own line in `/vendor/orders`.

---

## Phase 4 v5.8 — multi-product checkout (defensive fix)

### Error still appears after applying v5.8: `Attempted to lazy load [vendor] on model [Product]`
Almost certainly v5.8 wasn't actually deployed. Verify with this snippet:

```bash
docker compose exec app php artisan tinker --execute="
  \$src = file_get_contents(base_path('app/Domain/Order/CheckoutService.php'));
  echo str_contains(\$src, 'v5.8 — switched from loadMissing to load') ? 'v5.8 OK' : 'v5.8 MISSING';
"
```

If it prints `v5.8 MISSING`: the v5.8 tarball/zip wasn't extracted into the running container. Re-extract and rebuild.

If it prints `v5.8 OK` but the error still occurs: clear runtime caches and restart:
```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
docker compose restart app
```

If after all that the error still occurs: capture the FULL stack trace from the error page (every frame, not just the headline) and share it. There's a path none of the v5.x defenses cover, and the stack will point to it directly.

### Want to test multi-vendor checkout manually
After `php artisan migrate:fresh --seed`, sign in as `customer@marketplace.test`. Add 1 product from Demo Trading Co. + the Handwoven Beach Towel from Coastal Goods. Checkout → single order with 2 line items, different `vendor_id` per line. Each vendor signs in and sees only their own line in `/vendor/orders`.

---

## Phase 5 v6.0 — reviews, wishlist, payouts, shipping

### "You can only review products from delivered orders." error
Working as intended. To review a product, the customer must own an `order_item` for that product whose `order.delivered_at` is set. If you're testing: sign in as admin → mark the order as shipped, then delivered, via the OrderResource action; then return as the customer.

### Approved review doesn't appear on product page
1. Confirm the review's `status` is `approved` (not `pending`), in `/admin/product-reviews`.
2. Refresh the product page — the page is rendered server-side per request, no caching.
3. Confirm `products.rating_count` has incremented; if not, `ReviewService::recomputeProductRating()` didn't run. Re-approve the review.

### `Wishlist::firstOrCreate` race condition
The migration creates a unique index on `(user_id, product_id)`. Concurrent POSTs to `/wishlist/items` from the same user are safe — the DB enforces deduplication.

### Vendor wallet shows zero balance but I see paid orders
Balance is built from `order_items.vendor_earning_minor` filtered by:
- `orders.payment_status = 'paid'`
- For `released`: `delivered_at IS NOT NULL` **and** `earnings_release_at <= NOW()`

If `earnings_release_at` is in the future, the amount is in `releasing` (visible in the breakdown), not `released`. `OrderLifecycleService::markDelivered()` sets this to `NOW() + 7 days` by default.

### Vendor wallet shows lower available than expected
Look at `reserved_for_payout`. Pending and approved payout requests reserve their amount against the balance until they're paid (deducted permanently) or rejected (released back).

### Payout request fails: "Requested amount exceeds available balance"
The check uses **current** `available_balance` which already nets pending+approved requests. If you have a 10 KWD released balance and a 5 KWD pending request, your available is 5 KWD — you can't request another 6 KWD.

### Filament shows "Payout request approved" but vendor wallet still says pending
Browser cache or stale page. Refresh `/vendor/wallet`.

### Shipping methods don't appear on `/checkout`
The resolver matches the user's **default-address country**. If the customer has no default address, no methods show. Either:
1. The customer creates an address marked `is_default = true`, OR
2. Admin creates a global zone with `countries = ['*']` (the resolver falls back to this when no specific zone matches).

### Adding a custom shipping rule (e.g. per-vendor)
Out of Phase 5 scope. The `orders.shipping_method_id` column is on the order, not per-line. A Phase 6+ migration to `order_items.shipping_method_id` is the natural next step.

---

## Phase 5 v6.1 — navigation + lazy-load fixes

### Wishlist menu still doesn't show after applying v6.1
Verify `auth.user` is populated for the logged-in user. Open browser devtools → Network → click any page → look at the Inertia JSON response → `props.auth.user` should be an object with `id`, `name`, etc. If it's `null` while logged in, the session cookie isn't reaching `HandleInertiaRequests`. Clear cookies, log out, log back in.

### Vendor menu links (Reviews/Wallet/Payouts) still don't show
The links only render when `auth.user.vendor_status === 'approved'`. If the vendor is `pending`/`rejected`/`suspended`, the links are hidden by design — the dev sees only Dashboard/Products/Orders/Profile. Confirm the vendor's actual status in `/admin/vendors`. To approve a pending vendor: admin → Vendors → row action → Approve.

### "Attempted to lazy load [actor] on model [App\Models\OrderEvent]" after v6.1
v6.1 added `events.actor:id,name` to `OrderController::confirm()`. If you still see this:
1. Confirm the deployed file: `docker compose exec app grep "events.actor:id,name" app/Http/Controllers/OrderController.php` — should match.
2. If it matches but the error still occurs: capture the **full** stack trace (every frame). The error must be coming from a different controller — `VendorOrderController::show` loads events but doesn't iterate them; admin Filament uses its own eager loads. Share the trace.
3. Clear caches: `php artisan optimize:clear && docker compose restart app`.

### Admin Order Details page actions not showing
Header actions are visibility-gated by the order's current status AND the admin's permissions. Examples:
- "Mark delivered" only appears when `status === 'shipped'`. To get there: place a paid order → Confirm → Mark shipped → then Mark delivered becomes visible.
- "Refund" only appears when `payment_status` is `paid` or `partial_refund`.
- All actions also require the admin to have the matching permission (`orders.confirm`, `orders.ship`, `orders.deliver`, `payments.capture`, `payments.refund`, `orders.cancel`). `super_admin` has all of them via the `before()` hook; `admin_staff` may not, depending on the role's permission attachments.

### /vendor/payouts vs /vendor/wallet — which is "real"?
Both render the same page. `/vendor/wallet` is the canonical URL; `/vendor/payouts` is an alias added in v6.1 because the sidebar menu link reads "Payouts" and customers/developers expect the URL to match. The page contains the balance summary + payout request form + history table.

---

## Phase 5 v6.2 — EditOrder actions + demo wallet balance

### Admin Order Edit page still has no action buttons after v6.2
Verify the file got deployed:
```
docker compose exec app grep -c "Action::make('confirm')" app/Filament/Resources/OrderResource/Pages/EditOrder.php
```
Must print 1. If it prints 0, the v6.2 archive wasn't extracted into the container.

Visibility-gating: each action is shown only when the order's current status permits + the admin has the matching permission. Examples:
- **Mark delivered** appears only when `status === 'shipped'`. Walk an order through paid → Confirm → Mark shipped → Mark delivered.
- **Mark COD paid** appears only when payment_status=pending AND the order's last payment used the COD method.
- **Refund** appears only when payment_status is `paid` or `partial_refund`.

`super_admin` has all order permissions via the role's `before()` hook; `admin_staff` may not — confirm in Admin → Roles.

### Vendor wallet still shows 0 available balance after `migrate:fresh --seed`
Several possible causes:
1. **DemoSeeder didn't run.** Check `APP_ENV` in your `.env` — if it's `testing` or `production`, the seeder bails. Set to `local` and re-run `php artisan migrate:fresh --seed`.
2. **You placed additional test orders that aren't delivered yet.** Those sit in `in_escrow` and don't count toward `available`. They'll show in the in_escrow breakdown row.
3. **You've already requested all your available balance as payouts.** Check the `reserved_for_payout` row in the breakdown. If it equals `released`, your available is naturally zero. Rejecting a pending request returns its amount to available.

To verify the seed produced the expected balance:
```
docker compose exec app php artisan tinker --execute="
  \$v = App\Models\User::where('email','vendor@marketplace.test')->first()->vendor;
  print_r(app(App\Domain\Payout\VendorWalletService::class)->balanceFor(\$v));
"
```
Expect `available_balance_minor > 0` and `released_minor > 5000`.

### Wallet says "No funds available" even though I delivered an order
The cooling-off period is 7 days. Earnings move from `releasing` to `released` when `earnings_release_at <= now()` — set to `delivered_at + 7 days` by `OrderLifecycleService::markDelivered()`. To test payout flow without waiting, in tinker:
```
\$o = App\Models\Order::find(123);
\$o->update(['earnings_release_at' => now()->subDay()]);
```

---

## Phase 5 v6.3 — permission seeder + actionable orders

### `Spatie\Permission\Exceptions\PermissionDoesNotExist` during `migrate:fresh --seed`
This was the v6.2 → v6.3 trigger. Root cause: `RolesAndPermissionsSeeder::permissionCatalogue()` had duplicate PHP array keys; the later values silently overwrote the earlier ones; permissions like `orders.confirm` were never registered. v6.3 merges the duplicates.

To verify v6.3 is actually deployed:
```bash
docker compose exec app php artisan tinker --execute="
  \$cat = Database\Seeders\RolesAndPermissionsSeeder::permissionCatalogue();
  echo 'modules: '.count(\$cat).PHP_EOL;
  echo 'unique modules: '.count(array_unique(array_keys(\$cat))).PHP_EOL;
  echo 'orders.confirm in catalogue: '.var_export(in_array('orders.confirm', \$cat['orders'] ?? [], true), true).PHP_EOL;
"
```
All three lines should match (modules = unique modules; `true`).

### Admin opens order edit page and still sees no action buttons
Two possible causes:
1. **The order's status doesn't allow any action.** Action visibility is gated on status: Confirm needs `paid`, Ship needs `paid|confirmed`, Deliver needs `shipped`, Refund needs `paid|partial_refund`. Cancel needs not-yet-delivered. If the order is in `delivered` or `completed` status, no lifecycle button shows except possibly Refund. Open one of the seeded **DEMO-ACTIONABLE-PAID / CONFIRMED / SHIPPED / COD-PENDING** orders instead.
2. **The admin lacks the permission.** Run:
   ```bash
   docker compose exec app php artisan tinker --execute="
     \$a = App\Models\User::where('email','admin@marketplace.test')->first();
     echo 'super_admin: '.var_export(\$a->hasRole('super_admin'), true).PHP_EOL;
     foreach (['orders.confirm','orders.ship','orders.deliver','orders.cancel','orders.refund','payments.capture','payments.refund'] as \$p) {
        echo \$p.': '.var_export(\$a->can(\$p), true).PHP_EOL;
     }
   "
   ```
   All should print `true`. If any prints `false`, the v6.3 seeder didn't actually run; re-run `php artisan migrate:fresh --seed --force`.

### CI fails at the "v6.3 — migrate:fresh --seed succeeds" step
That's the step doing the actual runtime verification. The error output names the failing sub-check:
- "PermissionDoesNotExist: …" → catalogue is still missing a permission. Add it to the appropriate module group in `permissionCatalogue()`.
- "super_admin->can() returns FALSE for: …" → permission exists but isn't attached. Check the `$superAdmin->syncPermissions($allPermissions)` line ran.
- "no actionable demo order in X status" → `seedActionableOrdersForAdmin()` didn't run. Check `DemoSeeder::run()` calls it.

---

## Phase 5 v6.4 — admin orders eager-load

### `Attempted to lazy load [X] on model [App\Models\Order]` on `/admin/orders`
v6.4 expanded `OrderResource::getEloquentQuery()` to eager-load every relation accessed in any admin closure. If you still see this error after applying v6.4:

1. Verify the deployed file:
   ```bash
   docker compose exec app grep -A 10 "v6.4 — comprehensive" app/Filament/Resources/OrderResource.php
   ```
   Should show the expanded `with()` array.

2. If the missing relation is something NEW (not in v6.4's list), it means a future change added a `$record->X` access without updating the eager-load. Add it to both the `with()` array AND the Python cross-reference test in `Phase5V64RegressionTest.php`.

3. Clear caches: `docker compose exec app php artisan optimize:clear && docker compose restart app`.

### How to add a new relation access in admin closures (without reintroducing the bug)
1. Add the access (`$record->newRelation` etc.).
2. **Same PR:** add the relation to `OrderResource::getEloquentQuery()` `with()` array.
3. Add it to the assertion list in `Phase5V64RegressionTest.php` "every relation accessed in admin Order closures is in the eager-load chain" test.
4. The v6.4 CI step will catch any miss by GETing the admin pages under strict mode.

---

## Phase 6 — Dropshipping / Supplier Product Import (v7.0)

### "credentials column is plaintext in supplier_integrations"
**Cause:** `SupplierIntegration` model's `casts()` array missing `'credentials' => 'encrypted:array'`, OR `$hidden = ['credentials']` removed.
**Check:** `\DB::table('supplier_integrations')->where('id', $id)->value('credentials')` returns gibberish (encrypted blob), not your API key. The Phase 6 CI step asserts this directly.
**Fix:** Ensure the cast and `$hidden` are both set. The `maskedCredentials()` helper is the only safe way to display credentials in any UI.

### "Vendor cannot see Suppliers menu after Phase 6 install"
**Cause:** Vendor is not in `approved` status, so the `isApprovedVendor` block in `VendorLayout.tsx` hides the links.
**Fix:** Approve the vendor at `/admin/vendors/{id}` (super_admin can also `php artisan tinker` and set `$vendor->update(['status' => 'approved'])`).

### "Mapping form rejects valid prices"
**Cause:** `SupplierProductMapper::map()` enforces `$sellingPriceMinor >= $supplier_cost_minor`. The submitted price is in major units but supplier cost is stored as minor.
**Check:** On the mapping page, the form sends `price_major` which the controller converts to minor (cents) — `(int) round((float)$price_major * 100)`. Make sure currency-conversion isn't reducing the value below the supplier cost (in supplier currency).
**Fix:** Set a higher selling price, or adjust the supplier cost on the supplier product first.

### "Customer checkout for a dropshipping product did not create a supplier_order"
**Cause:** Either `Product::isDropship()` returned false (wrong `type` column value), or `DropshipOrderCreator` was never invoked from `CheckoutService::place()`.
**Check:**
1. `Product::find($productId)->type === 'dropship'` ✓
2. `app/Domain/Order/CheckoutService.php` has the line `app(\App\Domain\Supplier\DropshipOrderCreator::class)->createFromOrder($order);` after order item creation
3. The order item has `supplier_cost_minor` set (null = non-dropship)
**Fix:** Ensure mapping went through `SupplierProductMapper::map()` which sets `type=dropship` and `supplier_cost_minor` correctly.

### "Vendor sees other vendor's supplier orders"
**Cause:** A controller is querying `SupplierOrder::query()` without `->forVendor($vendor->id)`.
**Fix:** All vendor controllers must scope via the resolved `$vendor` attribute on the request. Test 11 in `Phase6DropshippingTest.php` covers this scenario.

### "CSV import succeeds but rows are not in DB"
**Cause:** Dry run was checked.
**Check:** `supplier_product_imports.dry_run` for the batch. If `true`, no rows were persisted by design.
**Fix:** Re-upload with the **Dry run** checkbox cleared.

## Phase 9 — Promotions / Coupons / Reviews / Support Tickets

### "Coupon code not found" / "Coupon has expired"

`CouponValidator` returns 11 distinct reasons; each maps 1:1 to a user-facing string in `CouponValidator::reasonMessage()`. To debug:

```bash
php artisan tinker --execute='echo \App\Models\Coupon::where("code","SAVE10")->first()?->toJson() ?: "not found";'
```

If the row is missing, re-run `php artisan db:seed --class=DemoSeeder`. If it exists but is_active=false or out of window, the validator correctly rejects.

### Coupon code stored uppercase even though I typed lowercase

By design. `Coupon::setCodeAttribute` uppercases on save; the validator's `strtoupper(trim($code))` matches case-insensitively. The Vendor coupon form uppercases on input too.

### "Promotion not appearing on /deals"

Three filters must pass: `is_active = true`, `approval_status = 'approved'`, and current time within `[starts_at, ends_at]`. Vendor-created promotions default to `pending` — they need admin approval via `/admin/promotions/{id}/edit` before going live.

```bash
php artisan tinker --execute='\App\Models\Promotion::usable()->get(["id","title","approval_status"])->each(fn($p)=>print($p->id.": ".$p->title." [".$p->approval_status."]\n"));'
```

### Support ticket number collision (`TKT-yymmdd-NNNN`)

`SupportTicket::generateNumber()` retries up to 5 times with a fresh random 4-digit suffix. With 10,000 possible suffixes per day, collision is overwhelmingly improbable. If it ever throws `RuntimeException: Could not generate unique ticket number`, you have either > 5000 tickets in a single day (queue capacity issue) or a clock drift problem.

### Ticket status flips backwards

Status transitions are computed in `SupportTicketService::reply` based on `author_role`. Customer reply → `pending` (queued for staff). Staff reply (admin or vendor) → `answered` (queued for customer). Admin can manually set `resolved` or `closed` via Filament. This is by design — if a customer replies after resolution, the ticket moves back to `pending` so it surfaces in the staff queue again.

### Review vendor_response doesn't appear

Three things must be true:
1. The review's `status` is `approved` (vendor responses on rejected reviews are hidden along with the review itself)
2. The product belongs to the vendor making the response (else 403 from `VendorReviewResponseController`)
3. The frontend product detail page is fetching `vendor_response` from the review (check the controller's serialization)

### Cart `discount_minor` doesn't match coupon's discount_value

It shouldn't directly. `discount_minor` is the **computed** discount in minor units after the `max_discount_minor` cap is applied and capped at the cart subtotal. For a `SAVE10` (10%) coupon with `max_discount_minor = 50000` on a 1,000,000 minor (1000 KWD) cart, the raw 10% = 100,000 minor but the cap kicks in → 50,000 minor.

## Phase 9 v9.1 — Correction notes

### "BindingResolutionException: [$s] was unresolvable"
v9.0 Filament `CouponResource` had `fn ($s) => ...` — Filament 3 injects closure parameters BY NAME (`$state`, `$record`, `$component`, `$get`, `$set`, `$livewire`, `$context`, `$operation`, ...). The name `$s` isn't in that list, and the parameter had no type hint, so the container couldn't resolve it. Fixed in v9.1 with `fn (?string $state): string => ...`. The CI sub-check `Phase 9 v9.1 — Filament closure parameters use Filament-injectable names` now catches any future regression project-wide.

### "I can't find the coupon input on the cart page"
v9.0 had the backend (`POST /cart/coupon`) but no UI. v9.1 added `CartCouponForm` to `Cart/Show.tsx` — sits in the summary aside below "Subtotal", has two modes (apply / remove). If the form doesn't appear, rebuild the frontend: `npm run build`.

### "Vendor can't confirm or deliver orders"
v9.0 only had `VendorOrderController::ship`. v9.1 added `confirm` + `deliver` actions (routes: `POST /vendor/orders/{order}/confirm` + `/deliver`). Buttons appear on `/vendor/orders/{id}` when the status allows the transition. If buttons are missing for an order, check `$canConfirm` / `$canDeliver` conditions in `Vendor/Orders/Show.tsx` — they require specific predecessor statuses.

### "I delivered an order but no Write Review button"
Three conditions: order's `delivered_at` is set (vendor must have clicked "Mark delivered"), the order_item has a `product_id`, and the customer hasn't already reviewed this product. Check `php artisan tinker --execute='echo \App\Models\ProductReview::where("user_id",X)->where("product_id",Y)->count();'` — if a row exists, the button won't show.

### "Admin support-ticket page lets me edit the customer's message"
This was the v9.0 bug. v9.1 replaced `EditSupportTicket` with `ViewSupportTicket` extending `ViewRecord`. If you still see an edit form, your deployment is stale — re-extract the v9.1 archive and `composer dump-autoload -o`. The artisan command `php artisan marketplace:version` reads the VERSION file and should print `Phase 9 v9.1`.

### "Mail/notification check still fails"
v9.1 verifies two things: `.env.example` carries `MAIL_MAILER=log`, AND no Phase 9 code path dispatches `Mail::`/`Notification::send`/`->notify()`. If your local `.env` overrides MAIL_MAILER to something else (smtp/mailgun) AND your transport is misconfigured, OTHER phases' notifications may fail — but no Phase 9 endpoint will. Check `grep MAIL_MAILER .env` and either set it to `log` or fix the transport.

## Phase 9 v9.3 — Correction notes

### "Coupon disappears on checkout page"
v9.1 stored the coupon on the cart but `CheckoutController::show` didn't include it in its props. v9.3 re-validates the cart's coupon server-side in the checkout controller (drops it silently if expired between cart-apply and checkout-load) and exposes a `coupon` block + `payable` field to `Checkout/Show.tsx`. If the line still doesn't appear after deploying v9.3, run `npm run build` and hard-refresh.

### "Vendor earnings + commission don't add up to what customer paid"
This was the v9.1 financial bug — commission was computed on the GROSS line total but the customer paid GROSS minus the coupon discount. v9.3's `CheckoutService` allocates the coupon across lines proportionally and computes commission on the NET (post-allocation) line total. The CI sub-check `Phase 9 v9.3 — Coupon allocation reconciliation invariant` asserts `sum(earning + commission) == subtotal − coupon_discount` for every coupon order in the seed.

### "I still see LazyLoadingViolationException on `/admin/support-tickets/{id}`"
v9.1's `ViewSupportTicket` didn't override `resolveRecord` — only the Infolist schema. Filament's default resolver does a plain `findOrFail` and the Infolist's `RepeatableEntry::make('messages')` accessed `user.name` per message → lazy-load → strict-mode throw. v9.3 overrides `resolveRecord` to eager-load `messages.user` + every relation the Infolist or list-page columns access. If you still see the error after v9.3, the deploy didn't update the Pages directory:
```bash
# Verify the fix is in the deployed file
grep -c 'public function resolveRecord' app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php
# expect: 1
grep -c 'messages.user' app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php
# expect: ≥ 1
```
If either is 0, re-extract the v9.3 archive and `composer dump-autoload -o`.

### "Write a Review button doesn't appear after delivery"
Three conditions must be true:
1. `order.delivered_at` is set (verify with `php artisan tinker --execute='dd(\App\Models\Order::find(X)->delivered_at);'`)
2. `order_item.product_id` is set and the product still exists
3. The customer hasn't already reviewed this product (check `product_reviews` table)

If all three are true and the button still doesn't appear, the frontend wasn't rebuilt — `npm run build`. Look for the test ID `write-review-button` in the rendered HTML to confirm the React component is rendering.

### "I want to see how the coupon discount was split per vendor"
Look at `/vendor/orders/{id}` as the vendor. The "Your portion" panel now shows:
- Gross subtotal (line_total sum for this vendor)
- Coupon — your share (sum of coupon_allocation_minor for this vendor's items)
- Net subtotal (gross − allocation)
- Platform commission (computed on net)
- Your earnings

For multi-vendor orders, each vendor sees only their own breakdown. The allocation is proportional to line value: a vendor with 70% of the order subtotal absorbs 70% of the coupon discount (with deterministic rounding for the last line).

## Phase 9 v9.4 — Codex audit response

### "MySQL error: Unknown operator 'ILIKE'"
v9.3 used PostgreSQL-only `ILIKE` in `CatalogController.php`. v9.4 replaced it with `whereRaw('LOWER(name) LIKE ?', [...])`. CI sub-check `Phase 9 v9.4 — Catalog search uses portable LOWER() LIKE` fails on any `'ILIKE'` occurrence in `app/` or `database/`.

### "Multi-vendor order: fulfillment status doesn't update when one vendor ships"
v9.3 had `OrderLifecycleService::refreshFulfillment` calling `$order->loadMissing('items')` after an in-place mass-update. `loadMissing` is a no-op when items is already loaded (which it was). The aggregate read stale in-memory statuses. v9.4 changes it to `$order->load('items')` (force-reload). Verify with the Pest scenario `multi-vendor order: partial ship updates aggregate fulfillment correctly`.

### "419 page expired" on cart/checkout HTTP tests after DemoSeederTest ran
v9.3 `DemoSeederTest` called `app()->detectEnvironment(fn () => 'local')` to bypass DemoSeeder's testing-env skip. This globally re-enabled CSRF middleware. Subsequent HTTP tests in the same Pest worker hit `/cart/items` and `/checkout` and got 419. v9.4 introduces `config('marketplace.allow_demo_seeder_in_testing')` as a scoped opt-in. The env stays `testing` throughout; CSRF stays disabled.

### "Call to a member function info() on null" in seeders
v9.3 `PaymentMethodsSeeder.php:76` called `$this->command->info(...)`. When invoked from `$this->seed(...)` in tests, `$this->command` is null → fatal. v9.4 patches that line + audits 8 other seeders. CI sub-check `Phase 9 v9.4 — Seeders are null-safe on $this->command` enforces project-wide.

### "Authorization scanner false-matched controllers that only mention authorize() in comments"
v9.3 `AuthorizationRegressionTest.php:58` used plain `str_contains($src, '$this->authorize(')` which matched docblocks. v9.4 strips `T_COMMENT` + `T_DOC_COMMENT` tokens via `token_get_all` before the search.

### "Pest test that uses ->toContain($x, 'message') always fails"
Pest's `toContain` treats every arg as a needle. The 'message' was being asserted as another value the array must contain — impossible. v9.4 removes the second argument from `AuthorizationRegressionTest` and moves the explanation into the `it()` description.

## Phase 9 v9.5 — Review approval lazy-load fix

### "I approved a review in admin but it doesn't appear on the product page"

This was the v9.5 root-cause bug. `AppServiceProvider` enables strict lazy-loading; `ReviewService::approve` accessed `$review->product` inside its transaction; the Filament resource didn't eager-load `product` for the action record; strict mode threw and the transaction rolled back.

To confirm v9.5 deployed correctly:

```bash
grep -c "loadMissing('product')" app/Domain/Review/ReviewService.php
# expect: 1

grep -c "getEloquentQuery" app/Filament/Resources/ProductReviewResource.php
# expect: 1
```

If either is 0, re-extract the v9.5 archive and `composer dump-autoload -o`.

### "Customer doesn't see their own pending review on the product page"

This is the intended behaviour: only approved reviews are publicly visible. The success flash after submission says "Thanks for your review! It will appear once approved." A future phase will add a `/my-reviews` page so customers can track their submission's moderation status, but for v9.5 the flash message is the only visibility into pending state.

### "Rating shows 5 not 5.0 in the JSON response"

`rating` is cast as `integer` in `ProductReview` (single-digit star count). The product-level aggregate `rating_avg` IS a float (decimal:2). If your test expects `"5.0"` as a formatted string, update it to compare numerically: `expect($rating)->toEqual(5);` works for both integer and float.

### "Vendor approval flow shows 0% commission"

Verified safe in v9.5 — a CI sub-check now asserts every approved vendor in the seed has a non-zero commission. If your real deployment produces 0%, check:

```bash
php artisan tinker --execute='
  foreach (\App\Models\Vendor::where("status","approved")->get() as $v) {
      $pkg = $v->currentPackage();
      echo $v->business_name . ": " . ($pkg ? $pkg->name . " @ " . $pkg->default_admin_commission_percent . "%" : "NO PACKAGE") . "\n";
  }
'
```

If a vendor shows "NO PACKAGE", their `vendor_subscriptions` row is missing or expired. The `VendorApprovalService::approve` creates one, but if you manually flipped a vendor to approved via DB, you'd skip that path.

## Phase 10 — Reports, SEO, sitemap, robots

### "My vendor sees data from other vendors on /vendor/reports"

This would be a severe security regression. v9.5 + Phase 10 enforce a triple defense:
1. The `vendor:approved` middleware sets the vendor object on the request attributes
2. `VendorReportsController::index` reads from `$request->attributes->get('vendor')` — never from a request param
3. The static CI check refuses any code change that adds `->input('vendor_id')` or `vendor_id` query-param reading to `VendorReportsController`

If you observe leak, check that the deployed VendorReportsController matches the shipped archive:

```bash
grep -A 3 "public function index" app/Http/Controllers/Vendor/VendorReportsController.php
# Should show: $vendor = $request->attributes->get('vendor');
# Should NOT show: $request->input('vendor_id') or $request->vendor_id
```

If the file diverged from the archive, restore it from `marketplace-phase-10.tar.gz`.

### "Customer / vendor sees /admin/reports"

The `viewReports` Gate (registered in `AppServiceProvider::boot`) checks Spatie's `reports.view` permission. Only `super_admin` and `admin_staff` have it (per `RolesAndPermissionsSeeder`). If a vendor or customer somehow has access:

```bash
php artisan tinker --execute='
  $u = \App\Models\User::where("email", "<email>")->first();
  echo "Roles: " . $u->roles->pluck("name")->implode(", ") . "\n";
  echo "Has reports.view permission: " . ($u->hasPermissionTo("reports.view") ? "YES" : "NO") . "\n";
'
```

If the user has `reports.view` and shouldn't, revoke via:
```bash
php artisan tinker --execute='
  \App\Models\User::where("email", "<email>")->first()->revokePermissionTo("reports.view");
'
```

Then run `php artisan permission:cache-reset` to flush the Spatie cache.

### "Sitemap.xml shows draft products"

The `SitemapController` filters by `STATUS_PUBLISHED`. If drafts appear, either:
- The status column on the offending product is set incorrectly (check `php artisan tinker --execute='\App\Models\Product::find(N)->status'`)
- The deployed `SitemapController` has been modified (re-extract from the archive)
- Caching is stale (`php artisan cache:clear` + check the response Cache-Control header; the sitemap has 1-hour cache)

### "Sitemap.xml is empty or 404s"

```bash
# Verify the route exists
php artisan route:list | grep sitemap
# Should show: GET|HEAD  sitemap.xml ... public.sitemap

# Verify the controller responds locally
php artisan serve &
curl -i http://127.0.0.1:8000/sitemap.xml
```

If Nginx serves `/sitemap.xml` as a static file (it tries to find `/var/www/marketplace/public/sitemap.xml` on disk), the `try_files` block needs to route to `index.php`. Per the deployment guide:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

This makes Nginx try the disk path first, then fall back to Laravel's router. Since there's no `/public/sitemap.xml` file, the request reaches Laravel which renders the dynamic sitemap.

### "robots.txt sitemap URL is wrong (http instead of https, or wrong domain)"

The `RobotsController` uses `url('/sitemap.xml')` which resolves at runtime from `APP_URL`. Set `APP_URL=https://your-domain.example` in production `.env` and run `php artisan config:cache` to apply.

Behind a load balancer, the request may arrive as HTTP. Set `TRUSTED_PROXIES=*` in `.env` so Laravel sees the original HTTPS scheme.

### "Financial reconciliation banner shows on /admin/reports"

If the Phase 10 reports dashboard shows the red "Financial reconciliation drift detected" banner, the v9.3 invariant has failed somewhere. The banner shows two deltas:

- `allocation_delta_minor` — if non-zero, sum of `order_items.coupon_allocation_minor` doesn't equal `orders.coupon_discount_minor`
- `reconciliation_delta_minor` — if non-zero, sum of `commission + earning` doesn't equal `subtotal - coupon_discount`

Both should always be 0. To find the offending order(s):

```bash
php artisan tinker --execute='
  foreach (\App\Models\Order::whereNotIn("status", ["cancelled"])->with("items")->get() as $o) {
      $alloc = $o->items->sum("coupon_allocation_minor");
      $netItems = $o->items->sum("commission_amount_minor") + $o->items->sum("vendor_earning_minor");
      $netExpected = $o->subtotal_minor - $o->coupon_discount_minor;
      if ($alloc !== $o->coupon_discount_minor || $netItems !== $netExpected) {
          echo "Order #{$o->id} ({$o->number}): allocation={$alloc} vs coupon={$o->coupon_discount_minor}, net items={$netItems} vs expected={$netExpected}\n";
      }
  }
'
```

Then investigate that specific order's items. Most likely: a manual DB tweak bypassed the `CheckoutService::placeOrder` invariant, or a refund/cancellation path didn't update allocations.

### "CSV export downloads with garbled characters in Excel"

The Phase 10 CSV exports include a UTF-8 BOM (`\xEF\xBB\xBF`) as the first 3 bytes specifically so Excel opens them as UTF-8 without prompting for encoding. If you see garbled characters:

```bash
head -c 3 marketplace-orders-*.csv | xxd
# Expect: 00000000: efbb bf
```

If the BOM is missing, the deployed `ReportsController::exportOrdersCsv` has been modified. Re-extract from the Phase 10 archive.

### "CSV export times out or runs out of memory"

The exports use `Model::chunk(500, ...)` to stream rows. For very large windows (50k+ orders), increase the PHP memory limit + execution time, or use the queued-export pattern documented in `PHASE_10_KNOWN_LIMITATIONS.md`. For now, narrow the date range or split into multiple smaller exports.

## Phase 10 v10.1 — defects fixed in the correction release

### "Product creation crashes with MassAssignmentException [images]"

**Fixed in v10.1.** Root cause: `VendorProductController::store/update` validated `images` as part of the data array and then passed the FULL array to `Product::create($data)`. Eloquent threw because `images` isn't (and shouldn't be) in `Product::$fillable`.

If you see this after deploying v10.1:
- Verify VendorProductController has `unset($data['images']);` in both `store()` (line ~110) AND `update()` (line ~180):
  ```bash
  grep -c "unset(\$data\['images'\])" app/Http/Controllers/Vendor/VendorProductController.php
  # Should output 2
  ```
- If the count is < 2, the deployed file diverged from the v10.1 archive. Re-extract.
- DO NOT "fix" by adding `images` to `Product::$fillable` — that's the wrong direction. The unset() approach is correct.

### "Admin reports page is blank / 404 / can't find it"

**Fixed in v10.1.** Three things had to be true; v10.0 had all three wrong:

1. **Layout file exists**: `resources/js/Layouts/AdminLayout.tsx` must be present (v10.0 was missing it; the page imported a nonexistent module → blank screen).
   ```bash
   ls -la resources/js/Layouts/AdminLayout.tsx
   ```
2. **Filament nav item registered**: `app/Providers/Filament/AdminPanelProvider.php` must register the Reports Dashboard nav item:
   ```bash
   grep -c "Reports Dashboard" app/Providers/Filament/AdminPanelProvider.php
   # Should output 1
   ```
3. **Frontend built**: `npm run build` must have succeeded post-v10.1.
   ```bash
   npm run build
   # Should complete without errors mentioning AdminLayout
   ```

If all three are correct and the page still blank, check the browser console for JavaScript errors and `storage/logs/laravel.log` for backend errors.

### "Vendor reports unfindable"

**Fixed in v10.1.** The `VendorLayout` must have a Reports link:
```bash
grep "vendor-nav-reports" resources/js/Layouts/VendorLayout.tsx
# Should output 2 lines (desktop + mobile drawer)
```

### "Vendor can't update order status / can't see action buttons"

**Fixed in v10.1.** The list page `/vendor/orders` now has inline buttons:
```bash
grep -cE "row-(confirm|ship|deliver)-" resources/js/Pages/Vendor/Orders/Index.tsx
# Should output 3
```

If the buttons are present in code but invisible in the browser, check the conditional gates (the buttons only show for states where the action is valid — `canConfirm`, `canShip`, `canDeliver`). An order already shipped won't show "Ship" again.

### "Admin sees raw paths like vendors/123/logo.jpg instead of images"

**Fixed in v10.1.** The Filament VendorResource now uses `Forms\Components\Placeholder` with `VendorFileLinks::previewHtml`:
```bash
grep "VendorFileLinks::previewHtml" app/Filament/Resources/VendorResource.php
# Should match (4 occurrences: logo, banner, license, id)
```

For private files (license, ID), the link goes through `Admin\VendorFileController` via a signed URL. If clicking the link returns 403:
- Verify the URL hasn't expired (30-minute TTL)
- Verify the admin is actually logged in
- Verify the user has `super_admin` or `admin_staff` role
- Check `app('router')->has('admin.vendor-files.show')` returns true

### "Admin can't see selected vendor package"

**Fixed in v10.1.** The VendorResource form now shows a "Vendor-selected package (from application)" section + the table has a "Requested package" column. If missing after deploy:
```bash
grep -c "requested_package" app/Filament/Resources/VendorResource.php
# Should output 2 (one for placeholder, one for column)
```

### "/sitemap.xml doesn't exist / returns 404"

**Most likely deployment misconfig.** The route IS registered in `routes/web.php` (line 370 of the v10.1 archive). Common causes:

1. **Nginx serves /sitemap.xml as a static file before reaching Laravel.** Verify the nginx config has:
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```
   Without `/index.php?$query_string` as the fallback, nginx 404s any URL not present as a literal file in `public/`. The fix: ensure the try_files line ends with the Laravel front controller.

2. **Route cache stale.** After deploying v10.1, run:
   ```bash
   php artisan route:clear
   php artisan route:cache
   ```

3. **Routes file diverged.** Verify the v10.1 archive's routes/web.php has the sitemap route:
   ```bash
   grep -n "sitemap.xml" routes/web.php
   # Expected: line ~370, registers public.sitemap route
   ```

4. **Verify with curl bypassing nginx** (if you have shell access):
   ```bash
   php artisan serve --port=8001 &
   curl -i http://127.0.0.1:8001/sitemap.xml
   # Should return 200 + XML body
   kill %1
   ```
   If this works but `https://your-domain/sitemap.xml` 404s, the problem is nginx, not Laravel.

### "Mobile view broken / navbar overflows"

**Fixed in v10.1.** Both `StorefrontLayout` and `VendorLayout` now have hamburger menus. Verify after deploy:
```bash
grep "storefront-mobile-toggle" resources/js/Layouts/StorefrontLayout.tsx
grep "vendor-mobile-menu" resources/js/Layouts/VendorLayout.tsx
```

Both must match. If browsers still show the old layout, hard-refresh + clear Vite manifest cache:
```bash
npm run build
php artisan optimize:clear
```

### "Site is still slow after v10.1"

v10.1 ships the easy wins (cached translations, composite indexes). For deeper investigation:

1. Install Laravel Debugbar in dev:
   ```bash
   composer require barryvdh/laravel-debugbar --dev
   ```
2. Visit each suspicious page; the debugbar shows query counts + duration per query.
3. If a page shows N+1 queries, identify the unloaded relation and add `->with()` in the controller.
4. For production observability, install Sentry or Telescope (staging only).

If the slowness is at the infrastructure layer (slow DB connection, no opcache, no Redis), see `PHASE_10_v10.1_PERFORMANCE_FINDINGS.md` for the deployment-time tuning checklist.

## Phase 10 v10.2 — recovery release troubleshooting

### "I deployed v10.2 but I still see v10.0 (or v10.1) behavior"

Most common cause: a cache layer hasn't been invalidated. Run the v10.2 deploy script which handles every cache:

```bash
./scripts/deploy.sh
```

If you don't have/can't use the deploy script, run these in order:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build                       # critical — without this, browser serves OLD JS
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan filament:cache-components
php artisan permission:cache-reset
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
sudo systemctl reload php8.3-fpm    # critical if OPcache is enabled with validate_timestamps=0
```

Then in the browser: hard-refresh (Ctrl+Shift+R) AND DevTools → Network → check Disable cache.

### "I see the storefront footer shows v Phase 10 v10.0"

The browser is loading the old Vite bundle. Three things to check:

1. **Did `npm run build` run AND succeed?**
   ```bash
   npm run build
   # Should produce: vite v5.x.x building for production...
   #                 ✓ built in NNNs
   ```

2. **Did the build output update `public/build/manifest.json`?**
   ```bash
   stat public/build/manifest.json
   # Modification time should be from the most recent build
   ```

3. **Is the browser caching the old `app-XXXX.js`?**
   - DevTools → Network tab → check Disable cache
   - Hard-refresh (Ctrl+Shift+R)
   - If a CDN sits in front (Cloudflare), purge the cache for `/build/*`

### "marketplace:verify-fixes reports ✗ for some checks"

The deployed source does NOT contain the v10.2 fixes for those checks. Possible causes:

- The archive was extracted to a different directory than the running app
- Another `git pull` or deploy overwrote the v10.2 files
- The extract failed silently for permission reasons

Fix:
```bash
cd /var/www/marketplace      # the ACTUAL app directory
ls -la artisan VERSION       # confirm you're in the project root
tar -tzf /path/to/marketplace-phase-10-v10.2.tar.gz | head -5
# Should list: marketplace/, marketplace/VERSION, marketplace/artisan, ...

tar -xzf /path/to/marketplace-phase-10-v10.2.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
php artisan marketplace:verify-fixes
```

If `verify-fixes` is now all ✓, you're good.

### "marketplace:verify-fixes command not found"

The command file isn't auto-discovered. Check:

```bash
ls -la app/Console/Commands/VerifyFixesCommand.php
# Must exist

grep -n "withCommands" bootstrap/app.php
# Must show: ->withCommands([__DIR__.'/../app/Console/Commands'])

php artisan list | grep marketplace
# Must list: marketplace:verify-fixes
```

If the file is missing, re-extract the archive. If the file exists but the command isn't listed:
```bash
composer dump-autoload
php artisan optimize:clear
```

### "deploy.sh refuses to run / says source is missing fixes"

deploy.sh sanity-checks the source contains the v10.1 fix markers BEFORE doing anything. If it fails this check, you're not running v10.2. The error message says exactly which file is missing what marker. Re-extract.

### "I see `v Phase 10 v10.2` in the footer but a specific defect still appears"

If `marketplace:verify-fixes` is all ✓ AND the version banner shows v10.2 AND a defect still appears, share:

- Which exact defect (number 1-10 from the brief)
- Browser console screenshot
- DevTools Network tab screenshot of the failing request
- Output of `php artisan route:list | grep <relevant-route>`

This level of diagnostic narrows the issue to a specific runtime cause (browser cache, CDN, OPcache, or — rarely — a genuine missed bug). With that information, I can target the specific cause in v10.3.

## Phase 10 v10.3 — emergency correction troubleshooting

### "Admin opens vendor edit page and sees a 500 error / blank page"

This was THE bug for v10.1+v10.2. Root cause: `disableLabel(false)` is invalid Filament 3.x API → BadMethodCallException at form render.

Verify v10.3 fix is deployed:
```bash
grep -c '->disableLabel(' app/Filament/Resources/VendorResource.php
# Must output: 0
```

If 0 but form still crashes → check Laravel logs (`storage/logs/laravel.log`) for the actual exception. Likely a different invalid Filament API call. Share the exception + stack trace.

### "MassAssignmentException [images] still appears"

Verify v10.3 model guard is deployed:
```bash
grep -A2 'public function fill' app/Models/Product.php | head -5
# Must show: unset($attributes['images']);
```

If present but exception still fires → OPcache hasn't reloaded:
```bash
sudo systemctl restart php8.3-fpm
php artisan optimize:clear
```

### "Vendor order page has no dropdown"

Verify:
```bash
grep -c 'vendor-order-status-dropdown' resources/js/Pages/Vendor/Orders/Show.tsx
# Must output: 1
```

If 1 but dropdown not visible → Vite rebuild missed:
```bash
npm run build
# Hard-refresh browser
```

### "Mobile pages still overflow"

Verify the v10.3 CSS guards are in the BUILT bundle (not just source):
```bash
grep -c 'overflow-x-hidden' resources/css/app.css   # source: must be 1
grep -lr 'overflow-x-hidden' public/build/assets/   # built bundle: must list at least one file

# If source has it but built doesn't:
npm run build
```

### General "v10.3 fixes appear absent"

```bash
php artisan marketplace:verify-fixes
```

Must show 19 ✓ lines. If any ✗ → the deployed source is NOT v10.3. Re-extract.

## Phase 10 v10.5 — the silent build-blocker

If you still see ANY pre-v10.4 defect after deploying:

```bash
npm run typecheck
```

Expected: no output, exit 0.

If TS2344 / TS6133 / TS2503 errors appear → the deployed source is NOT v10.5. Verify:
```bash
php artisan marketplace:fingerprint
# Compare aggregate against PHASE_10_v10.5_PACKAGE_INTEGRITY.md canonical
```

If typecheck passes but `npm run build` fails → share the Vite output. Likely a different issue in code v10.5 didn't touch.

If typecheck + build both pass but defects persist → caches.
```bash
sudo systemctl restart php8.3-fpm
php artisan optimize:clear
# Browser: hard-refresh + DevTools → Network → Disable cache
```

## Phase 10 v10.7 — vendor file "File not found"

If admin sees "File not found" for vendor image documents (logo, banner) but PDFs work:

```bash
php artisan tinker --execute='echo Storage::disk(config("marketplace.vendor_public_disk"))->exists("vendors/" . App\Models\Vendor::find(1)->id . "/" . basename(App\Models\Vendor::find(1)->logo_path)) ? "exists" : "missing";'
```

If output is `missing`, the file lives on a different disk than the canonical public disk. The v10.7 `VendorFileResolver` probes legacy disks automatically, so after deploying v10.7 the admin page should resolve legacy files via the signed admin route fallback.

Still seeing "File not found" after v10.7 deploy:
- `grep -c "VendorFileResolver::resolve" app/Domain/Vendor/VendorFileLinks.php` must = 1
- `php artisan storage:link` must have run (only matters for new uploads on the public disk)
- `php artisan optimize:clear && php artisan config:cache` must have run after deploy

## Phase 10 v10.8 — promotion not applied

If the Deals page advertises a promotion but product cards, cart, and checkout show full price:

```bash
# 1. Confirm v10.8 deployed
cat VERSION
# Expected: Phase 10 v10.8

# 2. Confirm wiring
grep -c "PricingService" app/Http/Controllers/CatalogController.php   # Expected: 4
grep -c "PricingService" app/Http/Controllers/CartController.php      # Expected: 3
grep -c "PricingService" app/Domain/Order/CheckoutService.php         # Expected: 2

# 3. Confirm migration ran
php artisan migrate:status | grep v108
# Expected: ... add_phase10_v108_promotion_snapshot_columns ... Ran

# 4. Confirm an active usable promotion exists
php artisan tinker --execute='dd(App\Models\Promotion::usable()->get(["id","title","discount_type","discount_value","starts_at","ends_at","is_active","approval_status"]));'
# Expected: at least one row matching your seeded promotion
```

If the promotion exists in `usable()` but the cart still shows full price, run:

```bash
php artisan optimize:clear
php artisan view:clear
npm run build
```

The most common cause of "v10.8 not visible" is an unrebuilt frontend — the React Pages still embed the v10.7 props shape and ignore the new `final_price` / `promotion` fields.

## Phase 10 v10.9 — admin sees 403 on /admin/reports

If the admin sees "Reports Dashboard" in the Filament panel but clicking it returns 403:

```bash
# 1. Confirm v10.9 deployed
cat VERSION   # Expected: Phase 10 v10.9

# 2. Confirm the wiring
grep "canManageAdminReports" app/Models/User.php                            # Expected: 1 method definition
grep "canManageAdminReports" app/Providers/AppServiceProvider.php           # Expected: 1 Gate body usage
grep "canManageAdminReports" app/Providers/Filament/AdminPanelProvider.php  # Expected: 1 ->visible() usage

# 3. Confirm Gate::before super_admin shortcut
grep "Gate::before" app/Providers/AppServiceProvider.php

# 4. Confirm the self-healing migration ran
php artisan migrate:status | grep v109

# 5. Confirm the user actually has the role
php artisan tinker --execute='
  $u = App\Models\User::where("email","admin@marketplace.test")->first();
  echo "status: " . $u?->status . "\n";
  echo "roles: " . $u?->getRoleNames()?->implode(", ") . "\n";
  echo "canManageAdminReports: " . ($u?->canManageAdminReports() ? "true" : "false") . "\n";
'
```

If `canManageAdminReports` returns true but the route still 403s, the deployed source isn't actually v10.9. Re-extract the archive and re-run the deploy.

If `canManageAdminReports` returns false, the user is either inactive (`status !== 'active'`) or lacks both `super_admin` and `admin_staff` roles. Reassign:

```bash
php artisan tinker --execute='
  $u = App\Models\User::where("email","admin@marketplace.test")->first();
  $u->update(["status" => "active"]);
  if (! $u->hasRole("super_admin")) $u->assignRole("super_admin");
'
php artisan permission:cache-reset
```

## Phase 10 v10.10 — admin still sees 403 on /admin/reports

After deploying v10.10, if the admin STILL gets 403 on `/admin/reports`:

```bash
# 1. Confirm v10.10 deployed
cat VERSION   # Expected: Phase 10 v10.10

# 2. Confirm the v10.10 direct guard
grep "guardAdminReportsAccess" app/Http/Controllers/Admin/ReportsController.php
# Expected: 3 occurrences (1 method def + 2 call sites)

# 3. Run the v10.10 diagnostic — this PRINTS exactly what state the user is in
php artisan reports:diagnose-access YOUR_ADMIN_EMAIL
```

The diagnostic output identifies the exact failure cause: role not assigned, wrong role name, etc.

```bash
# 4. Repair (idempotent)
php artisan reports:repair-access YOUR_ADMIN_EMAIL

# 5. Logout, login as that admin, retest
```

If the diagnostic shows `canManageAdminReports(): true` but `/admin/reports` STILL returns 403, the deployed source isn\u0027t actually v10.10. Re-extract the archive and rerun deploy.

## Phase 10 v10.11 — runtime defect troubleshooting

### Admin /admin/reports still returns SQL error after v10.11 deploy

```bash
cat VERSION   # Must say: Phase 10 v10.11
grep -c "SUM(requested_amount_minor)" app/Domain/Reports/ReportsService.php   # Expected: 2
grep -c "SUM(amount_minor)" app/Domain/Reports/ReportsService.php             # Expected: 0
php artisan migrate                                                            # in case the db is on an older schema
php artisan optimize:clear
```

### Vendor order dropdown still grayed out

```bash
grep -c "computeStatusOptions" app/Http/Controllers/Vendor/VendorOrderController.php   # Expected: 2
grep -c "status_options" resources/js/Pages/Vendor/Orders/Show.tsx                     # Expected: >=4
grep -c "fulfillment_status === 'shipped'" resources/js/Pages/Vendor/Orders/Show.tsx   # Expected: 0
npm run build   # rebuild React after deploy
```

Hard-refresh the browser (Ctrl+Shift+R) to bypass cached `/build/assets/*.js`. Confirm the loaded asset filenames in the Network tab match `public/build/manifest.json`.

### Support ticket reply throws `LazyLoadingViolationException`

```bash
grep -c "messages.user:id,name,email" app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php   # Expected: 5
grep "redirect(\"/tickets/" app/Http/Controllers/SupportTicketController.php                                     # Expected: 1 line in reply()
grep "redirect(\"/vendor/tickets/" app/Http/Controllers/Vendor/VendorSupportTicketController.php                 # Expected: 1 line
```

### Site still feels slow

The §2 fix removes one specific overhead (`getAllPermissions()->pluck` on every render). It is NOT a comprehensive perf optimization. If the site is still slow after v10.11, look at:
- `php artisan optimize:clear` (clear stale caches)
- Redis connectivity — if the cache driver is `redis` but Redis isn\"t reachable from the host, every cache call falls through with TCP timeouts. Verify with: `php artisan tinker` → `Cache::put(\"test\", 1, 60); Cache::get(\"test\")` — should be instant.
- Query log (locally only): `DB::enableQueryLog()` in tinker, hit a page, then `DB::getQueryLog()` — look for >50 queries on any single page.

## Phase 10 v10.12 — admin /admin/reports still SQL-errors after deploy

### Symptom

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column \"role\" in \"WHERE\"
```

### Diagnosis

```bash
cat VERSION                                                                 # → Phase 10 v10.12
grep -n \"User::role(.customer.)\" app/Domain/Reports/ReportsService.php    # → 1 line (the v10.12 fix)
grep -rnE \"DB::table\\([\\\"\\047]users[\\\"\\047]\\)->where\\([\\\"\\047]role[\\\"\\047]\" app/  # → 0 lines
```

If any of the above don\"t match expectations, the deployed source isn\"t actually v10.12. Re-extract the archive and run:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan permission:cache-reset
```

Then restart PHP-FPM / `php artisan serve`.

If the deployed source IS v10.12 but the error persists, check `php artisan migrate:status` — the Spatie pivot tables (`roles`, `model_has_roles`) must exist. If they don\"t, run `php artisan migrate` to apply the Spatie migration that lives in `database/migrations/...create_permission_tables*.php`.

## Phase 10 v10.13 — Vendor Reports nav link still not visible after deploy

### Symptom

Approved vendor logs in. Cannot see a Reports link in the vendor navigation. The dashboard does or does not show the indigo CTA card.

### Diagnosis

```bash
cat VERSION                                                                           # → Phase 10 v10.13
grep -c \"ReportsIcon\" resources/js/Layouts/VendorLayout.tsx                          # → 4
grep -c \"vendor-dashboard-reports-cta\" resources/js/Pages/Vendor/Dashboard.tsx       # → 1
grep -c \"icon: 'reports'\" resources/js/Layouts/VendorLayout.tsx                # → 1
```

If all of these return the expected count but the link is still not visible: **stale browser assets**. The browser is loading an older Vite-built bundle from cache.

### Fix the stale-asset problem

```bash
npm run build                                                                # rebuild bundles
ls -la public/build/assets/*.js | head -5                                    # confirm fresh mtime
cat public/build/manifest.json | head                                        # note the new asset filenames
```

In the browser:
1. Open Network tab.
2. Hard-refresh (Ctrl+Shift+R / Cmd+Shift+R).
3. Confirm the `.js` files loaded by `/build/assets/*.js` match the names in `public/build/manifest.json`.

If a service worker is registered for the site, unregister it or clear via DevTools → Application → Service Workers.

### Vendor is approved but the CTA card doesn\"t show

The CTA shows only when `vendor.status === \"approved\"`. Verify:

```bash
php artisan tinker
>>> \\App\\Models\\Vendor::where(\"user_id\", THE_VENDOR_USER_ID)->first()->status
# Expected: \"approved\"
```

If the status is anything else (\"pending\", \"rejected\", \"suspended\", \"closed\"), the CTA correctly hides — those vendors are also blocked from `/vendor/reports` by the route middleware. Use `php artisan tinker` to set status to \"approved\" for testing:

```php
\\App\\Models\\Vendor::where(\"user_id\", THE_VENDOR_USER_ID)->update([\"status\" => \"approved\"]);
```

## Phase 10 v10.14 — pages still slow after deploy

### Diagnosis sequence

```bash
# 1. Confirm v10.14 deployed
cat VERSION                          # → Phase 10 v10.14

# 2. Confirm migrations applied
php artisan migrate:status | grep v1014   # should be "Ran"

# 3. Confirm Inertia share is scope-aware
grep -c "str_starts_with(\$path, 'admin/')" app/Http/Middleware/HandleInertiaRequests.php
# Expected: 2 (cart_summary + top_categories)

# 4. Confirm homepage health is cached
grep -c "marketplace:homepage_health:v1" app/Http/Controllers/HomeController.php
# Expected: 1
```

### If admin pages still fire carts/categories queries

Likely a stale opcache. Restart PHP-FPM (or `php artisan optimize:clear` for the artisan serve dev server).

### If all pages are still slow uniformly

Infrastructure issue, not code. Check Redis connectivity:
```bash
php artisan tinker
>>> Cache::put("perf_test", 1, 60); Cache::get("perf_test")
```
Should return 1 instantly. If slow, cache driver is misconfigured. Common: `CACHE_STORE=redis` with `REDIS_HOST=redis` (Docker hostname) when running directly on the host. Change `REDIS_HOST=127.0.0.1` or `CACHE_STORE=file`.

## Phase 10 v10.15 — Customer login broken or other share() failure

### Diagnosis sequence

```bash
# 1. Confirm v10.15 deployed
cat VERSION                                    # → Phase 10 v10.15

# 2. Look for defensive-catch entries in the log
tail -100 storage/logs/laravel.log | grep "v10.15 defensive catch"
```

If grep returns entries, v10.15 IS catching exceptions in share() — the message field contains the underlying cause. Common patterns:

| Log message contains | Likely cause | Fix |
|---|---|---|
| "Connection refused", "connect_timeout", "could not connect" | Redis unreachable (when CACHE_STORE=redis) | Change `CACHE_STORE=file` in `.env`, run `php artisan optimize:clear` |
| "No such file or directory" + a cache path | `CACHE_STORE=file` directory missing or permissions | `mkdir -p storage/framework/cache/data && chmod -R 775 storage` |
| "Base table or view not found: 'cache'" | `CACHE_STORE=database` table missing | `php artisan cache:table && php artisan migrate` |
| "Class App\Models\Vendor not found" or relation errors | Composer autoload stale | `composer dump-autoload` |
| Spatie permission errors | Permission cache out of sync | `php artisan permission:cache-reset` |

### If no v10.15 catch entries appear but login is still broken

The issue is OUTSIDE the wrapped closures. Most likely:

1. **Session cookies not being set/sent**:
   - DevTools → Application → Cookies → check XSRF-TOKEN and session cookies
   - `.env`: `SESSION_SECURE_COOKIE=false` for HTTP testing, `SESSION_DOMAIN` unset (or matches your host)
2. **CSRF token mismatch (419)**:
   - `php artisan optimize:clear`, then hard-refresh the login page so a new token is issued
3. **Wrong project running**:
   - `php artisan route:list | grep -i login` — confirm `/login` routes are registered from the deployed source
   - Inspect `public/build/manifest.json` mtime — should be after `npm run build`
4. **Vite dev server interfering**:
   - Stop any running `npm run dev` and use `npm run build` + serve from `public/`
5. **Stale opcache**:
   - `sudo systemctl restart php8.3-fpm` (production) OR Ctrl+C and re-run `php artisan serve` (dev)

## Phase 10 v10.16 — Blank homepage after login or build

### Diagnosis

```bash
# 1. Confirm v10.16 deployed
cat VERSION                                    # → Phase 10 v10.16

# 2. Open browser DevTools → Console while loading /
# If you see: TypeError: Cannot read properties of undefined (reading 'length')
# pointing at the Welcome bundle, v10.16 is NOT applied (still pre-v10.16 code)

# 3. Confirm fresh assets are deployed
ls -la public/build/manifest.json
# Compare browser Network /build/assets/*.js filenames with manifest
```

### Common causes if blank page persists after v10.16

1. **Stale build**: `npm run build` was not re-run after deploy. Run it, then hard-refresh.
2. **Service worker** caching old assets. Unregister via DevTools → Application → Service Workers.
3. **Reverse proxy / CDN** serving cached old bundle. Flush.
4. **Different unsafe access** elsewhere in the bundle. Console will show a different file/line — apply the same `?? []` pattern there.

If the Console error is anything OTHER than `permissions`, it's not the v10.16 fix surface. Identify the file/line from the trace and apply defensive normalization at that site.
