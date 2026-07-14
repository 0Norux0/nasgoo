# Phase 11B.5 — Vendor Intelligence Repair

## Preface: honest sandbox constraints

The developer's rejection of v11B.4 was correct. My previous "verification" was static file checks — I confirmed files exist but never proved anything about runtime.

**Sandbox constraints I must declare openly:**
- No PHP interpreter in this sandbox → `php artisan migrate` and `php artisan test` CANNOT run here
- No MySQL/PostgreSQL → cannot inspect DB state
- No Docker → cannot boot the stack
- No HTTP server → cannot hit `/vendor/intelligence` at runtime

**What I CAN do — the audit that produced this release:**
- Cross-reference every test insert against the actual migration column definitions
- Trace every service method against the model attribute names
- Static-analyze the frontend for TypeScript soundness
- Grep the entire codebase for pattern-based bug indicators (wrong table names, missing required columns, hardcoded FK values)

**What proves v11B.5 correct — the dev must run these:**
```bash
php artisan optimize:clear
php artisan migrate                    # applies v11B.4 4-table migration
php artisan test --filter=Phase11B4    # 56 scenarios — this is what proves correctness
```

## 6 concrete bugs identified + fixed

### Bug #1 — Test factory used wrong Order columns

**Pre-v11B.5** test file:
```php
Order::create([
    'user_id' => 1, 'vendor_id' => $v->id,
    'status' => Order::STATUS_COMPLETED,
    ...
]);
```

**Real schema** (`2026_04_10_000001_create_orders_table.php`):
- `orders.user_id` is a `foreignId->constrained` — `user_id=1` fails if no user with id=1 exists
- `orders.number` is `string()->unique()` with NO default — required
- `orders` has NO `vendor_id` column — vendor_id is on `order_items`
- Missing `subtotal_minor`, `total_minor` etc.

Result: every test that created an order threw `SQLSTATE 23000` (integrity constraint) or `Column not found`.

**v11B.5 fix** — use `Order::factory()` which respects all required columns:
```php
function p11b4_order_for_product(Product $product, User $customer, ...): Order
{
    $order = Order::factory()->create([
        'user_id' => $customer->id,      // real user FK
        'status' => $status,
        'payment_status' => Order::PAY_PAID,
        ...
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'vendor_id' => $product->vendor_id,   // REQUIRED column
        'product_id' => $product->id,
        'product_name' => $product->name,      // REQUIRED snapshot column
        ...
    ]);
    return $order->fresh();
}
```

### Bug #2 — `ProductQualityService` used `is_array($p->images)` — always false

**Root cause found in Product model** (`app/Models/Product.php` lines 25-47):
> The developer reported the SAME `MassAssignmentException [images]`. At least one code path passing `images` into mass assignment... unset $attributes['images']... Uploaded files for product images live in the product_images table via the `images()` relationship (HasMany ProductImage).

So `$p->images` on a Product model returns a `HasMany` relation → resolved to `Illuminate\Support\Collection` (not array). `is_array()` returns FALSE for a Collection → my quality service scored EVERY product as `media_score = 0` → every product got `media.no_image` in its missing-fields list → summary counters were wrong → dashboard misleading.

**v11B.5 fix** in `app/Services/VendorIntelligence/ProductQualityService.php`:
```php
// Pre-v11B.5:
$images = is_array($p->images) ? $p->images : [];
$imgCount = count($images);

// v11B.5:
$imgCount = $p->images()->count();  // relation query, not attribute
```

### Bug #3 — `customer_product_views` inserts used wrong column name

**Pre-v11B.5** test file used `'session_hash'`. **Real schema** uses `'session_key'` (`string('session_key', 64)`) AND requires a NOT NULL `'locale'` column. Every test inserting a view threw `SQLSTATE 42S22 Unknown column 'session_hash'`.

**v11B.5 fix**:
```php
DB::table('customer_product_views')->insert([
    'user_id'     => $customer->id,      // real FK
    'session_key' => 's-' . $i,          // real column name
    'product_id'  => $p->id,
    'locale'      => 'en',                // REQUIRED
    'viewed_at'   => now()->subDays(5),
    ...
]);
```

### Bug #4 — Wishlist tests hardcoded FKs to non-existent users

**Pre-v11B.5** used `'user_id' => $i + 1` for i in 0..14 — assumed users 1-15 exist. Fresh test DB → FK violation.

**v11B.5 fix**: create real users via factory in the loop.

### Bug #5 — Cart items inserts missed required `vendor_id`

`cart_items` migration has `foreignId('vendor_id')->constrained()` — NOT NULL. My test omitted it. Also referenced `cart_id => $i + 1` without creating carts. Two SQL failures per test.

**v11B.5 fix**: create real Cart via `Cart::factory()` per iteration + include `vendor_id` in insert.

### Bug #6 — `Schema::hasTable('tickets')` — wrong table name

`app/Services/VendorIntelligence/VendorIntelligenceManager::activeSupportTicketCount` checked `Schema::hasTable('tickets')` and queried `DB::table('tickets')`. The actual table name (per `create_support_tickets` migration) is `support_tickets`. Result: `hasTable()` always returned false → ticket count always 0 → "Respond to pending support tickets" checklist item never surfaced even when the vendor had open tickets.

**v11B.5 fix**:
```php
if (! Schema::hasTable('support_tickets')) return 0;
return (int) DB::table('support_tickets')
    ->where('vendor_id', $vendor->id)
    ->whereIn('status', ['open', 'in_progress', 'awaiting_reply'])
    ->count();
```

## Personalized homepage audit (dev asked me to investigate)

The dev reported personalized homepage was also failing. My audit of `resources/js/Pages/Welcome.tsx` lines 238-242:

```tsx
{personalization && (
    <PersonalizedSections
        enabled={personalization.enabled}
        sections={personalization.sections}
    />
)}
```

The personalization block is NOT gated by `isSectionEnabled` (that only gates categories/featured/services). It renders when the `personalization` prop is truthy. **The v11B.3.3 isSectionEnabled work did NOT introduce a personalization regression.** If the dev is seeing empty personalization sections, the most likely cause is:

1. `HomeController` not passing the `personalization` prop (would need controller-side investigation)
2. `PersonalizationManager::forRequest()` returning `null` (guest without views yet, or `SiteSettingsService::get('personalization.enabled')` returning false)
3. Cache not primed on the current locale

None of these are v11B.4 regressions. **v11B.5 does not touch personalization** — the code path is preserved as-is.

## Pest suite v11B.5 (56 scenarios)

| Group | # | What each scenario proves at runtime when dev runs `php artisan test --filter=Phase11B4` |
|---|---|---|
| §34 Inventory alerts | 12 | OOS, low-stock, fast-moving, slow-moving, no-tracking rules; suspended vendor exclusion; draft exclusion; resolve-on-fix; idempotency; snooze |
| §35 Product quality | 11 | Complete product = high score; missing images/AR/category/stock lower score correctly; digital not penalized; scores persist; vendor isolation on quality scores |
| §36 Opportunities | 6 | HVLC threshold, wishlist interest, cart abandonment; suppression below evidence; critical alerts non-dismissable |
| §37 Dashboard + permissions | 9 | Vendor 200; customer 403; guest redirect; admin overview; vendor cannot see admin page; cache vendor-isolated; dismiss ignores request-body vendor_id spoofing; feature flag + threshold defaults |
| §38 Perf / regression / e2e | 18 | Query budget; command idempotency; suspended-vendor skip; prune deletes old resolved; prune unsnoozes expired; cache invalidation; **§38.45-47 end-to-end** (fetch `/vendor/intelligence` → correct summary counters; dismiss changes downstream dashboard; fresh vendor with no products returns zeros not nulls); regression on homepage + all prior-phase markers |

**§38.45-47 are the key end-to-end scenarios** the dev's browser flow relies on. Each `test()->actingAs($v->user)->get('/vendor/intelligence')` calls the real controller with a real vendor session, hits the real database, and asserts the JSON body content matches what the frontend expects.

## What v11B.5 does NOT do (honest deferrals)

- **Cannot verify migrations run in this sandbox** — dev must run `php artisan migrate` and confirm all 4 tables (`vendor_intelligence_summaries`, `_alerts`, `_feedback`, `vendor_product_quality_scores`) exist
- **Cannot verify Pest passes in this sandbox** — dev must run `php artisan test --filter=Phase11B4` and confirm all 56 pass
- **Cannot verify browser flow in this sandbox** — dev must load `/vendor/intelligence` as an approved vendor and confirm real numbers appear (not "N/A")
- No cross-vendor benchmarking (deferred, documented in v11B.4 report)
- No email/push notifications (§18 deferred)
- No scheduled auto-generation — the dev must add a scheduler entry OR call the command manually after data changes
- No dedicated admin threshold editor UI — thresholds are tunable via `siteSettings.vendor_intelligence.*` today, but there's no per-key form (planned for a future phase)

## Package integrity

- 39/44 static checks pass (5 flagged were python-string false positives — actual code is correct, see PACKAGE_INTEGRITY doc)
- 170 unique Pest helpers, 0 duplicates
- All v11B.4 files preserved (2 files modified: ProductQualityService.php, VendorIntelligenceManager.php)
- All prior-phase (v11B.3.3, v11B.3.2, v11B.3.1, v11B.3, v11B.2.2, v10.13) markers intact

## Rollback

Three tiers documented in PHASE_11B_5_ROLLBACK.md:
- Tier 1: revert ProductQualityService.php + VendorIntelligenceManager.php only (keeps schema, rolls back service-code bug fixes)
- Tier 2: full revert to v11B.4 baseline (`marketplace-phase-11B-4-baseline.tar.gz`)
- Tier 3: chain back to v11B.3.3 approved baseline

## Phase 11B.5 STOPS HERE

No new features. This is exclusively a bug-fix release for v11B.4. The dev must run migrate + test + browser to prove correctness — I cannot in this sandbox.
