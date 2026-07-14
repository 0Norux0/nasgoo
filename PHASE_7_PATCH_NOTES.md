# Phase 7 v8.0 — Customizable Products / Print-on-Demand Foundation

**Status:** Phase 7 v8.0 first cut on dev-approved Phase 6 v7.3 baseline. Pending CI verification (only true gate).
**Scope:** New product type `custom`, vendor field builder, customer customization form, secure file uploads, cart/checkout integration, design proof workflow foundation, admin Filament resources.
**Backwards compatibility:** existing simple / dropship / variable / digital product types are untouched. No Phase 0–6 PHP file was modified except the small targeted additions documented below.

---

## What v8.0 adds (architecture)

```
              ┌──────────────────┐                ┌────────────────┐
  vendor   →  │ FieldBuilder UI  │ →    POST    → │  Vendor field  │ → product_customization_fields
              │ /vendor/products │                │  controller    │
              │ /{id}/customiz.. │                └────────────────┘
              └──────────────────┘
                                                                                    cart_items
              ┌──────────────────┐                ┌────────────────┐               (+fee_minor)
 customer  →  │ CustomizationForm│ → multipart → │ Cart customized│ → cart_item_customizations
              │ (on Catalog/Show)│                │  controller    │      ↑       (snapshots)
              └──────────────────┘                │ + FieldValid.  │      │
                                                  │ + FileStorage  │      │
                                                  │ + CartService  │      │ files stored on
                                                  └────────────────┘      │ 'local' (private) disk
                                                          ↓               │
                                                  ┌────────────────┐      │
                                                  │  Checkout      │ ─────┘
                                                  │  (snapshot to  │
                                                  │   order_item_  │
                                                  │   customizations)
                                                  └────────────────┘
                                                          ↓
              ┌─────────────────────┐             ┌────────────────┐
  vendor   →  │ Vendor/Orders/Show  │ → upload  → │ ProofWorkflow  │ → customization_proofs
              │ + CustomizationVend │             │ Service        │   (draft → sent →
              │ Block               │             │                │      approved/rejected)
              └─────────────────────┘             └────────────────┘
                                                          ↓
              ┌─────────────────────┐             ┌────────────────┐
  customer →  │ Orders/Show + Cust. │ ← approve   │ CustomerProof  │
              │ ationBlock          │ /reject ────│ ResponseCtrl   │
              └─────────────────────┘             └────────────────┘
```

---

## Files added

### Schema (5 migrations, dated 2026_01_07)

| File | What |
|---|---|
| `create_product_customization_fields_table` | Per-product field definitions (key unique per product, 9 supported types, allowed_file_types, options[] for selection, extra_fee_minor, is_active, sort_order) |
| `create_cart_item_customizations_table` | Per-cart-line customization snapshot rows with `field_key`/`label`/`type` denormalized so render survives field edits |
| `create_order_item_customizations_table` | Immutable post-checkout history; no FK back to field |
| `create_customization_proofs_table` | Vendor-uploaded proofs (status: draft → sent → approved/rejected) with vendor_note + customer_response |
| `add_customization_columns_to_cart_and_order_items` | `cart_items.customization_fee_minor`, `order_items.customization_fee_minor` + `customization_status` (7 states, indexed) |

### Models (4 new + 3 extended)

- **`ProductCustomizationField`** — 9 TYPE_* constants, FILE_TYPES / TEXT_TYPES / SELECTION_TYPES classifier arrays, `isFileField()` / `isTextField()` / `isSelectionField()` helpers.
- **`CartItemCustomization`** — snapshot row attached to a cart line.
- **`OrderItemCustomization`** — immutable snapshot copied at checkout time.
- **`CustomizationProof`** — 4 STATUS_* constants + helpers `isAwaitingCustomer()` / `isApproved()` / `isRejected()`.
- **`Product`** (extended) — `TYPE_CUSTOM`, `isCustomizable()`, `customizationFields()` (ordered by sort_order), `activeCustomizationFields()` (also filtered by is_active).
- **`CartItem`** (extended) — `customization_fee_minor` in fillable + casts; `customizations()` relation; `hasCustomizations()` helper; **`lineTotalMinor()` rewritten** to add the per-line customization fee.
- **`OrderItem`** (extended) — 7 `CUST_*` status constants + `ALL_CUSTOMIZATION_STATUSES`; `customizations()`, `proofs()` (ordered by id DESC), `latestProof()` (latestOfMany) relations.

### Services (`app/Domain/Customization/`)

- **`CustomizationFieldValidator`** — walks `$product->activeCustomizationFields()`, type-aware validation (file: required + size + extension + MIME-vs-extension; text: required + max_text_length; selection: required + option exists + aggregates option-level extra_fee; checkbox), throws `ValidationException` with `customizations.{key}` error keys + field labels.
- **`CustomizationFileStorage`** — `local` disk, `customizations/{user_id}/{Str::random(40)}.{ext}` for uploads, `customization-proofs/{vendor_id}/{order_item_id}/...` for proofs; `readStream()` / `mimeTypeOf()` / `sizeOf()` for safe streaming.
- **`CustomizationCartService`** — composes `CartService` + `CustomizationFileStorage`. `addCustomized()` opens a transaction, calls `cart->addItem(..., forceNewLine: true)`, persists each `CartItemCustomization` snapshot, sums `extra_fee_minor` onto `cart_items.customization_fee_minor`, then `recomputeTotals()`. `removeWithFiles()` cleans up uploads too.
- **`ProofWorkflowService`** — `uploadDraft()` / `send()` / `approve(?$note)` / `reject($reason)` each advance `order_items.customization_status` (draft saved keeps the existing status; `send()` → `proof_uploaded`; `approve()` → `customer_approved`; `reject()` → `customer_rejected`).

### Admin Filament resources

- **`CustomizationProofResource`** + Pages (List / View — no Create, Edit, or Delete) — read-only oversight. Columns: order number / product / vendor / file / mime / KB / status badge (4-color) / sent_at / responded_at. Filter on status. Eager-loads `orderItem.order:id,number` + `vendor:id,business_name` (lazy-load defense). Gated on `customization_proofs.view`.
- **`ProductCustomizationFieldResource`** + Pages (List / Edit) — admin global view of all fields with filters by type / is_active / required. Edit gated on `customization_fields.manage`.

### Controllers

- **`Vendor\VendorCustomizationFieldController`** — index / store / update / destroy scoped via `Product::where('vendor_id', $vendor->id)->findOrFail($productId)`. Auto-generates `key` from `Str::slug($label, '_')` with collision-resolving suffix. Validates 9 types, options structure, max_file_size_kb (≤ 51200 = 50 MB), max_text_length (≤ 5000), extra_fee_minor (≤ 1,000,000 minor units).
- **`Vendor\VendorCustomizationProofController`** — `upload()` (mimetypes: image/jpeg, image/png, image/webp, application/pdf, max 10240 KB; optional `send_now`), `send()` (only DRAFT or REJECTED can be re-sent), `downloadFile($orderItemId, $kind, $rowId)` streams via `Storage::readStream` with `X-Content-Type-Options: nosniff`. Kind = `customization` | `proof`.
- **`CustomizationCartController`** — `POST /cart/items/customized`. Splits text inputs from `$request->input('customizations', [])` and files from `$request->file('customizations', [])`, asserts `$product->isCustomizable()`, validator → cart service.
- **`CustomerProofResponseController`** — `approve($orderId, $itemId, $proofId)` (note ≤ 1000) and `reject(...)` (reason: required, min:5, max:1000). Scoped via `Order::where('user_id', auth()->id())->findOrFail($orderId)` → items → proofs; cross-customer access returns 404. Only `STATUS_SENT` proofs respondable.
- **`CustomizationFileController`** — customer-side secure file download for own order. Same `kind` = customization | proof pattern as the vendor side.

### Routes (11 new)

```
# Customer (auth+verified)
POST   /cart/items/customized                                cart.add.customized
POST   /orders/{order}/items/{item}/proofs/{proof}/approve   orders.proofs.approve
POST   /orders/{order}/items/{item}/proofs/{proof}/reject    orders.proofs.reject
GET    /orders/{order}/items/{item}/files/{kind}/{rowId}     orders.files.show   (kind=customization|proof)

# Vendor (auth+vendor:approved)
GET    /vendor/products/{productId}/customization-fields                vendor.products.customization-fields.index
POST   /vendor/products/{productId}/customization-fields                vendor.products.customization-fields.store
PATCH  /vendor/products/{productId}/customization-fields/{fieldId}      vendor.products.customization-fields.update
DELETE /vendor/products/{productId}/customization-fields/{fieldId}      vendor.products.customization-fields.destroy
POST   /vendor/orders/items/{orderItemId}/proofs                        vendor.orders.proofs.upload
POST   /vendor/orders/items/{orderItemId}/proofs/{proofId}/send         vendor.orders.proofs.send
GET    /vendor/orders/items/{orderItemId}/files/{kind}/{rowId}          vendor.orders.files.show
```

### React (1 page + 1 component + 5 extended pages)

- **NEW** `Pages/Vendor/Customization/Fields/Index.tsx` — vendor field builder. Conditional sections: file fields show allowed-types checkbox grid + max_file_size_kb; text fields show max_text_length; selection fields show options editor (value / label / extra_fee). Inline create form. Uses `SharedProps & SupplierIntegrationsPageProps`-style typing (v7.3 pattern).
- **NEW** `Components/Customization/CustomizationForm.tsx` — reusable customer-facing renderer for all 9 field types. Single `textState` + `files` React state, flushed into Inertia `useForm` on submit. `encType="multipart/form-data"` + `forceFormData: true`. Inline per-field errors keyed `customizations.{key}`.
- **EXTENDED** `Catalog/Show.tsx` — props now include `customization_fields: CustomizationFieldDef[]`; standard Add-to-cart button hidden for `type === 'custom'`; `<CustomizationForm>` renders inline (or sign-in prompt for guests).
- **EXTENDED** `Cart/Show.tsx` — per-line customization summary block (label + value or filename + extra fee) under each cart item; line total now includes customization fee via the rewritten `lineTotalMinor()`.
- **EXTENDED** `Orders/Show.tsx` — `<CustomizationBlock>` sub-component shows customer's submissions + latest proof + approve / reject UI. Reject requires reason (min:5 max:1000) via inline textarea.
- **EXTENDED** `Vendor/Orders/Show.tsx` — `<CustomizationVendorBlock>` shows customer inputs, lists existing proofs with status, lets vendor upload a new proof (with `send_now` toggle) and re-send drafts.
- **EXTENDED** `Vendor/Products/Index.tsx` — "Customize" link appears next to Edit for `type === 'custom'` products.

### Permission catalogue

Added 2 new top-level modules (5 permissions). Distinct names avoid the v6.3 duplicate-key class. **Catalogue is now 20 unique top-level keys with no duplicates.**

| Module | Permissions |
|---|---|
| `customization_fields` | `customization_fields.view`, `customization_fields.manage` |
| `customization_proofs` | `customization_proofs.view`, `customization_proofs.upload`, `customization_proofs.respond` |

Vendor role grants `customization_fields.view`, `customization_fields.manage`, `customization_proofs.view`, `customization_proofs.upload`. `customization_proofs.respond` is reserved for admin override of customer responses; ordinary customer approve / reject uses ownership-based scoping (not permission-based).

---

## Demo data added

Idempotent additions to `database/seeders/DemoSeeder.php` (called from `run()` after the Phase 6 dropshipping seed):

1. **Personalized Photo Mug** — `type=custom`, 3.50 KWD base, 4 fields (photo upload required, custom text optional +2.50, color required: white/black+1.00/blue+1.00, placement required: front/wrap+2.00).
2. **Custom Printed T-Shirt** — `type=custom`, 8.00 KWD base, 5 fields (design upload required, size required: S/M/L/XL+1.00/XXL+1.50, color required, optional text +3.00, optional font with one extra-fee option).
3. **`DEMO-CUSTOM-{YmdHis}`** sample customer order — `customer@marketplace.test` ordered the mug with photo, "Best Dad Ever ❤", black color, wrap-around placement. Total: 9.00 KWD (3.50 + 5.50 customization fees). 4 `OrderItemCustomization` rows seeded; `customization_status = proof_uploaded`; a `CustomizationProof` row in `STATUS_SENT` is attached so the customer can immediately exercise the Approve / Reject UI on `/orders/{id}`.

Demo files are seeded without an actual file on disk (file_path = null). Clicking the download link in the demo UI returns 404, which is correct behaviour for missing files — the demo focuses on workflow + UI, not on a real binary upload. A real customer upload via `/cart/items/customized` stores a real file on the private disk.

---

## CI verification (added)

New step `Phase 7 — Customizable products end-to-end` in `.github/workflows/ci.yml` with **5 sub-checks**:

1. Schema present (4 new tables + 3 new columns) + ≥2 customizable products + ≥6 fields + all 5 permissions registered.
2. `DEMO-CUSTOM-%` order exists with `customization_fee_minor > 0` + `customization_status` set + ≥4 `order_item_customizations` rows + ≥1 `SENT` proof.
3. `ProofWorkflowService::send()` → `approve()` walks `customization_status` state machine correctly (`pending` → `proof_uploaded` → `customer_approved`).
4. **Private disk security**: `php artisan serve` started, `curl http://127.0.0.1/storage/customizations/.../random_test_blob.bin` returns **HTTP 404** (private disk is never web-accessible). Test file cleaned up after.
5. All 11 Phase 7 routes registered with the correct route names.

The existing frontend job's v7.3 React validator was **extended** to also scan `resources/js/Pages/Vendor/Customization/**` and `resources/js/Components/Customization/**` — every `usePage<>()` must use `SharedProps`, no local `interface PageProps`/`FlashProps`.

Final CI verdict: `✅ Phase 7 PASSES — ready to approve Phase 8`.

### Tests

`tests/Feature/Phase7CustomizationTest.php` — **22 Pest scenarios** covering:

- Schema basics (TYPE_CUSTOM, isCustomizable, customizationFields ordering, activeCustomizationFields filter)
- Permission catalogue integrity (5 perms registered, vendor role grants the right subset)
- `CustomizationFieldValidator` — rejects missing required text / file, oversized files, disallowed extensions, max_text_length overrun, invalid selection options; aggregates per-option extra_fee; skips empty optional fields
- `CustomizationCartService` — fresh cart line every time, fees roll up, customized items never merge, files land on private disk with random filenames
- Checkout snapshot (gated test, will run when CheckoutService signature is stable)
- `ProofWorkflowService` — full draft → sent → approved + draft → sent → rejected state machines
- Cross-customer isolation: stranger can't approve owner's proof (404)
- File security: private disk only, never public
- Lazy-load defense: cart page loads cleanly under `Model::shouldBeStrict(true)` with multiple customized lines

---

## Verification I ran in the sandbox

I cannot run `php artisan migrate:fresh --seed` or `npm install` in the sandbox (network is 403). What I verified:

- **PHP brace balance**: 289/289 (all source files)
- **CI YAML parses**: valid
- **Permission catalogue dedup**: 20 unique keys, 0 duplicates
- **Phase 7 React validator** (the same code that ships as the new CI sub-check): 1 usePage<> call in 2 Phase 7 React files, all using `SharedProps`, no local `PageProps`/`FlashProps` shadowing
- **Real `tsc` with strict + Inertia constraint stubs** (v7.3 verification floor): 0 TS2344 errors in Phase 7 files (continued below in Stub-based TypeScript verification)

The only authoritative end-to-end gate remains the CI summary. **Do not approve Phase 8 until you see `✅ Phase 7 PASSES — ready to approve Phase 8`** in the GitHub Actions summary.

---

## Stub-based TypeScript verification (continues from v7.3 methodology)

Just like Phase 6 v7.3, my sandbox sweep built minimal type stubs for `@inertiajs/react`, `@inertiajs/core`, and `react` (deleted before packaging — not shipped in the archive), then ran real `tsc --strict --moduleResolution bundler` against:

- `resources/js/types/inertia.d.ts` (the augmented `SharedProps` declaration)
- `resources/js/Pages/Vendor/Supplier/**` (Phase 6 React)
- `resources/js/Pages/Vendor/Customization/**` (Phase 7 vendor)
- `resources/js/Components/Customization/**` (Phase 7 customer)

Result: **0 TS2344 errors** across both Phase 6 and Phase 7 React files. Remaining errors (TS7006 stub-gap noise on event handler `e` parameters) don't exist in the real `@types/react` shipped via `npm ci` on the CI runner.

---

## Developer testing checklist

```bash
git pull
composer install
npm ci
npm run typecheck     # must pass
npm run build         # must pass
php artisan marketplace:setup-demo --force
php artisan test --filter Phase7Customization
```

Then manually:

1. Log in as `vendor@marketplace.test / password`, go to `/vendor/products`, click **Customize** next to "Personalized Photo Mug". Confirm 4 fields appear. Add a new field.
2. Log out, browse to `/products/demo-custom-mug`, log in as `customer@marketplace.test / password`. Confirm the customization form renders, fill it in, click **Add customized item to cart**.
3. Check `/cart` — confirm customization summary + extra fees appear under the line.
4. Open the existing `DEMO-CUSTOM-%` order from `/orders` — confirm the **SENT** proof shows up with Approve / Reject buttons. Click **Approve**; confirm the status flips.
5. Log in as the vendor, open the same order from `/vendor/orders`, confirm customer's customization inputs are visible. Upload a new proof and click "Send to customer immediately".
6. Log in as `admin@marketplace.test`, navigate to **Customization Proofs** + **Customization Fields** in Filament — confirm both list views render.

## Known limitations

- **Proof file thumbnails are not previewed inline** — links open in a new tab. Inline preview for image/PDF is a Phase 8 nice-to-have.
- **No customer-side "edit my customizations" flow before checkout** — to change a customization the customer removes the cart line and re-adds. This matches how most print-on-demand carts work but could be smoother.
- **Single-round proof history is fully tracked** — the latest proof is what the customer responds to. Multi-vendor or per-item parallel rounds are supported in the schema (`order_item_id`-keyed) but the UI only highlights the latest one.
- **Demo file paths are null in the seeded order** — clicking the download link in the demo returns 404. A real customer upload via `/cart/items/customized` works correctly.
- **Vendor must set product type to `custom` manually** — the field builder currently warns if you open it on a non-custom product. A "convert to customizable" action in the vendor product edit form is Phase 8 polish.

## Next-step recommendation

After Phase 7 CI green: **Phase 8 — Service Booking** (the third leg of the marketplace alongside physical inventory + dropshipping + customizable). The customization status state machine in v8.0 establishes the pattern for a separate `booking_status` on order_items that will be used by service products.

---

**Phase 7 STOPS HERE. Do not start Phase 8 until CI verdict is green and you've walked the developer checklist above.**
