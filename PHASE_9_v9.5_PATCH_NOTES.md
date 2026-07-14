# Phase 9 v9.5 — Correction Package

**Status:** Targeted fix for the manually-confirmed review-display bug + disciplined re-verification of Codex audit findings against the v9.4 baseline.

**Read `PHASE_9_v9.5_VERIFICATION_MATRIX.md` first.** It justifies every accept/reject decision against actual code evidence.

---

## The one real production fix

### Bug: "Approved reviews don't appear on the product page"

**Confirmed by the developer's manual site test.** Root cause is a strict-mode lazy-load that rolls back the approval transaction silently.

#### The exact path

1. Admin opens `/admin/product-reviews` → Filament list page renders, each row is a `ProductReview` model **without** the `product` relation eager-loaded.
2. Admin clicks "Approve" on a row → Filament passes that bare `ProductReview` to the action handler.
3. Action handler calls `ReviewService::approve($record, auth()->user())`.
4. Inside the service's `DB::transaction`, the code accesses `$review->product` to call `recomputeProductRating($review->product)`.
5. `AppServiceProvider.php:22` has `Model::shouldBeStrict(! app()->isProduction())` — strict mode is ON in development/testing.
6. `$review->product` triggers a lazy-load → **`LazyLoadingViolationException`** thrown.
7. Transaction rolls back. Review stays at `pending`. Product rating not refreshed.
8. Filament shows an error notification (or, on some Filament versions, the error is silently swallowed and the success toast still appears — making the symptom look like "approval worked but the page doesn't reflect it").
9. Customer refreshes the product page → no reviews visible → bug reported.

#### The fix (two-layer defense)

**Layer 1 — service-level** (`app/Domain/Review/ReviewService.php::approve`):

```php
public function approve(ProductReview $review, User $admin, ?string $notes = null): ProductReview
{
    if (! $review->isPending()) {
        throw new RuntimeException(...);
    }

    // Phase 9 v9.5 — eager-load product BEFORE the transaction so the
    // strict-mode guard doesn't roll us back on $review->product access.
    $review->loadMissing('product');

    return DB::transaction(function () use ($review, $admin, $notes) {
        ...
        $this->recomputeProductRating($review->product);
        ...
    });
}
```

`loadMissing` is a no-op when the relation IS already loaded, so callers that did pre-load `product` don't pay for a duplicate query.

**Layer 2 — Filament resource boundary** (`app/Filament/Resources/ProductReviewResource.php`):

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with([
        'product:id,name,slug,vendor_id,rating_avg,rating_count',
        'user:id,name,email',
        'orderItem:id,order_id,product_id',
    ]);
}
```

This ensures that every page and every row-level action on the resource receives records with relations already loaded. Defense at the boundary, not just deep in the call stack.

---

## Why this didn't fail until v9.5

The reviews integration tests across v9.0–v9.4 exercised the **submission** path with strict mode on, but the **approval** path was tested without `preventLazyLoading(true)`. So in test runs, `$review->product` lazy-loaded silently. In the developer's runtime — where the AppServiceProvider toggle was on — the same code threw.

v9.5's Pest test explicitly enables `Model::preventLazyLoading(true)` before calling `ReviewService::approve` to match the production runtime exactly. A second scenario does the full HTTP round trip (GET product page before approval → 0 reviews → approve → GET product page after → 1 review + rating updated).

---

## Codex audit findings — re-verification summary

| Category | Count |
|---|---|
| **Production defect (fixed in v9.5)** | 1 (review approval lazy-load) |
| **Already-resolved in earlier release** | 5 |
| **Verified and holds** | 3 |
| **False positive (no change)** | 1 |
| **N/A (code/test doesn't exist in this codebase)** | 3 |
| **Stub/env limitation (not a code defect)** | 3 |

Full evidence in `PHASE_9_v9.5_VERIFICATION_MATRIX.md`. Highlights:

- **Vendor package commission fallback**: verified safe. New CI sub-check asserts no seeded vendor has 0% commission.
- **Cart-item vendor_id derivation**: verified server-side. New Pest scenario tries to spoof `vendor_id: 99999` in the request and confirms the server still uses the product's actual vendor.
- **ILIKE / MySQL compatibility**: already fixed in v9.4. New v9.5 scenario re-asserts.
- **Filament closure safety**: already fixed in v9.1. v9.1's CI sub-check still passes (0 bad closures).

---

## Files touched in v9.5

```
app/Domain/Review/ReviewService.php                              loadMissing('product') before transaction
app/Filament/Resources/ProductReviewResource.php                 getEloquentQuery() eager-loads product/user/orderItem
tests/Feature/Phase9V95RegressionTest.php                        NEW (6 scenarios)
.github/workflows/ci.yml                                          3 new v9.5 sub-checks
VERSION                                                           Phase 9 v9.4 → Phase 9 v9.5
PHASE_9_v9.5_PATCH_NOTES.md                                       NEW
PHASE_9_v9.5_VERIFICATION_MATRIX.md                               NEW
PHASE_9_v9.5_DEVELOPER_CHECKLIST.md                               NEW
PHASE_9_v9.5_KNOWN_LIMITATIONS.md                                 NEW
```

**0 v9.4 code touched** — every prior-release fix preserved (coupon persistence/allocation, lazy-load on tickets, Write Review wiring, ILIKE fix, refreshFulfillment force-reload, seeder null-safety, DemoSeeder scoped opt-in, scanner comment-strip, etc.).

---

## Counts

| | v9.4 → v9.5 |
|---|---|
| Phase 9 CI sub-checks | 19 → **22** (3 v9.0 + 6 v9.1 + 5 v9.3 + 5 v9.4 + 3 v9.5) |
| Phase 9 Pest scenarios | 49 → **55** (24 + 11 + 10 + 4 + 6) |
| Phase-specific CI sub-checks (grand total) | 53 → **56** |
| Unique global test helpers | 39 → **43** (4 new `p95_`, 0 duplicates) |

---

## Defenses re-run (all pass on v9.5)

- v8.5 unique global helpers: 43 unique, 0 duplicates ✓
- v8.7 controller return types: 58 Inertia methods, 0 mismatches ✓
- v9.1 Filament closure injection: 0 bad closures ✓
- v9.4 ILIKE absence: 0 in app/ or database/ ✓
- v9.4 Seeder null-safety: 0 unsafe `$this->command->` ✓

---

## v9.5 STOPS HERE — do not start Phase 10

Approval requires real CI to produce:

```
✅ Phase 9 v9.5 PASSES — ready to approve Phase 10
```

Until then, this is a candidate, not a release.
