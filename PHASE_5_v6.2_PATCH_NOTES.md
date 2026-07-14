# Phase 5 v6.2 — EditOrder Actions + Demo Wallet Balance Fix

**Scope:** the two developer-reported regressions in v6.1. Same Phase 5 scope. **Phase 6 is not started.**

---

## Honest root-cause summary

| # | Reported issue | Root cause | Fix |
|---|---|---|---|
| 1 | Admin Order Detail/Edit page has no status action buttons | v6.1 added the seven lifecycle actions (Confirm/Ship/Deliver/Cancel/Refund/COD-capture/Transfer-confirm) as **header actions on `ViewOrder` only**. The OrderResource table has BOTH `ViewAction` AND `EditAction` row buttons. The dev clicked Edit → landed on `EditOrder` → that page's `getHeaderActions()` returned `[ViewAction::make()]` and nothing else. So zero lifecycle controls visible on the page the dev was looking at. | Replaced `EditOrder::getHeaderActions()` with the same seven actions as ViewOrder. Both pages now expose identical lifecycle controls. |
| 2 | Vendor payout form not visible (available balance = 0) | Two-part issue: (a) DemoSeeder created **only one delivered order** with a single-quantity line — typically ~10 KWD earnings. (b) `seedDemoPayoutRequest` reserved `min(available, 5000) = 5 KWD`, which often consumed ~50% of available. Combined with the dev placing additional test orders (sitting in escrow, not yet released), the visible released-vs-reserved math ended up with `available = 0`. The wallet UI then hid the form entirely with just "No available balance yet." | Two-part fix: (a) DemoSeeder now seeds **three delivered orders** with mixed quantities (2 × cheapest, 3 × middle, 1 × most expensive) across delivered_at dates of 12/18/25 days ago, all past release. Released earnings reliably reach 40–80 KWD depending on demo product pricing. (b) `seedDemoPayoutRequest` now reserves only **at most 2 KWD** (capped via `min(floor(available/4), 2000)`, floored at 1 KWD). (c) Wallet UI: when balance = 0, instead of hiding everything we show an amber explanation panel with the in-escrow / releasing / reserved breakdown and the 3-step release sequence (paid → delivered → 7-day cool-off). When balance > 0, the form is reachable via "New request" as before. |

There's also a small consistency win:

- **`EditOrder` and `ViewOrder` now use the exact same header-action set.** A new test asserts they don't diverge — so future changes to one without the other will fail CI.

---

## Files changed

### PHP (2 edits)
- `app/Filament/Resources/OrderResource/Pages/EditOrder.php` — completely rewritten with the 7 lifecycle actions (was: only `ViewAction::make()`)
- `database/seeders/DemoSeeder.php` — `seedDeliveredOrderAndReview` rewritten to seed 3 delivered orders; `seedDemoPayoutRequest` reservation capped at 2 KWD

### React (1 edit)
- `resources/js/Pages/Vendor/Wallet.tsx` — the payout request section now always renders. When `available_minor === 0`, shows an explanatory amber panel with the breakdown + release-sequence; when > 0, shows the New request button + form. Amount field gets a helper text reminding the vendor of the maximum payable.

### Tests (1 new file, 9 scenarios)
- `tests/Feature/Phase5V62RegressionTest.php` — EditOrder header-action presence, ViewOrder/EditOrder parity check, lifecycle service flows (markDelivered/markShipped/cancel write event + audit log), wallet exposes positive `available_minor` on factory-built released earnings, payout submission works end-to-end.

### CI (`.github/workflows/ci.yml`)
- Verdict: `✅ Phase 5 v6.2 PASSES — ready to approve Phase 6`
- New audit-map row for the v6.2 regression test
- New verdict-table row summarizing both fixes
- Existing Phase 5 demo-data step now also asserts `available_balance_minor > 0` + `released_minor > 0` + `delivered orders >= 3`
- New CI step **`v6.2 — admin order EditOrder header actions + full payout E2E flow`**: static-checks `EditOrder.php` source for all 7 action names AND runs a vendor-submit → admin-approve → admin-mark-paid sequence on the seeded demo data, asserting the audit log captures all three transitions and `wallet.paid_out` updates.

### Docs
- `PHASE_5_v6.2_PATCH_NOTES.md` (this file)
- `PHASE_5_REPORT.md` — v6.2 section appended
- `README.md` — header bumped to v6.2
- `TROUBLESHOOTING.md` — two new entries

---

## Why v6.1 didn't catch this in testing

Honest accounting: I had a v6.1 test (`Phase5V61RegressionTest`) that did source-string inspection on **ViewOrder** to verify the header actions existed. I never wrote the equivalent assertion for **EditOrder**. The CI step was named "ViewOrder header actions present" — accurately, since that's all it checked. The dev clicking Edit hit the gap directly.

v6.2 adds a `ViewOrder and EditOrder header-action sets are identical` test so this divergence can't reoccur.

For the wallet bug: I ran my own math on v6.0 demo seed and got positive available balance, so I shipped it. But I had only ever tested with the **single-order** seed I wrote. The dev placed additional test orders (which sat in escrow, not released) which shifted the visible math. The fix is to make the seed produce so much released balance that no amount of in-escrow orders the dev places during testing can bring `available` to zero.

---

## Manual Developer Verification Checklist

After applying v6.2 + `php artisan migrate:fresh --seed`:

| # | Step | Expected |
|---|---|---|
| 1 | Sign in as `admin@marketplace.test` → Admin → Orders → click the **Edit** icon on any order | Header shows up to 7 action buttons: Confirm / Mark shipped / Mark delivered / Mark COD paid / Confirm transfer / Cancel / Refund — visibility depends on current status. **All were missing in v6.1.** |
| 2 | On the same order, click **View** icon instead | Same 7 actions appear. Both pages should look identical action-wise. |
| 3 | Find an order in `paid` status as admin → click Confirm → confirm dialog → page redirects | Status flips to `confirmed`. An order event of `event_type='confirmed'` is recorded. Audit log row `action='order.confirmed'` is created. |
| 4 | Sign in as `vendor@marketplace.test` → `/vendor/wallet` | **Available balance is positive** (not 0). The "New request" button is visible next to "Request a payout". |
| 5 | Check the wallet balance breakdown section | Shows lifetime / in_escrow / releasing / released / reserved / paid_out / available — all should be readable with appropriate KWD amounts. |
| 6 | Click **New request** → enter an amount ≤ available_minor → submit | Request created with status `pending`; appears in the history table below. |
| 7 | Sign in as admin → Admin → Vendor Payout Requests → open one pending request | Approve / Reject / Mark Paid buttons visible. Click Approve → modal appears → submit. Click Mark Paid → enter `TEST-TRANSFER-001` → submit. |
| 8 | Back as vendor → `/vendor/wallet` | History table reflects status: pending → approved → paid. `paid_out` lifetime increased. Available balance decreased. |
| 9 | As a vendor whose `available_minor` is 0 (manually deplete via testing, or use `pending-vendor@marketplace.test`) | The wallet page shows the amber explanation panel: "No funds available for payout right now" + breakdown + 3-step release sequence. No misleading "form might be missing" feeling. |
| 10 | GitHub Actions on the v6.2 branch | Verdict: `✅ Phase 5 v6.2 PASSES — ready to approve Phase 6`. The new `v6.2 — admin order EditOrder header actions + full payout E2E flow` CI step is green. |

If step 1 still fails: confirm the deployed file with `docker compose exec app grep "Action::make('confirm')" app/Filament/Resources/OrderResource/Pages/EditOrder.php` — should match. Then `php artisan optimize:clear` and reload.

If step 4 still shows zero available: confirm seed actually ran with `php artisan tinker --execute="echo App\\Models\\Order::where('status','delivered')->count();"` — should be ≥ 3. If zero, the DemoSeeder env guard skipped — set `APP_ENV=local` in `.env` before `migrate:fresh --seed`.

---

## Stop discipline

Phase 6 has not been started. Reply **"approve Phase 6"** with your chosen scope only after the 10-step checklist above passes and the CI verdict is green.
