# Phase 10 v10.11 — Developer Checklist

The dev's §6 manual runtime verification process for the 4 confirmed defects.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.11.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.11.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

## §6 — required pre-test commands

```bash
php artisan optimize:clear
php artisan route:list --path=vendor/orders     # → 8-10 routes including show/confirm/ship/deliver
php artisan route:list --path=admin/reports     # → 2 routes (index + export)
php artisan test --filter='Phase10V1011'        # → 17 Pest scenarios — all should pass
npm run typecheck
npm run build
```

Restart Laravel from this exact project folder:

```bash
# Ctrl+C the existing artisan serve, then:
php artisan serve
```

If using PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

Restart queue worker if applicable. Hard-refresh the browser (Ctrl+Shift+R). Confirm the loaded asset filenames in the Network tab match `public/build/manifest.json`.

## The 4 required confirmations

### ☐ Confirmation A — §5 `/admin/reports` opens with no SQL error

1. Login as admin.
2. Visit `/admin/reports`.
3. **Expected:** HTTP 200. The page renders with KPI cards including the **Payouts** section (pending / approved / paid / rejected with amounts and counts).

If the dev's database has zero payout rows, all cards show 0 — that's correct, not a regression.

**Failure path:** If you still see `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'amount_minor'`, the deployed source is not actually v10.11. Run:
```bash
grep -c "SUM(requested_amount_minor)" app/Domain/Reports/ReportsService.php
# Expected: 2 (admin summary + per-vendor query)
grep -c "SUM(amount_minor)" app/Domain/Reports/ReportsService.php
# Expected: 0
```

### ☐ Confirmation B — §3 Vendor order-status dropdown is enabled

1. Login as a vendor with at least one order.
2. Open `/vendor/orders` then click into a specific order.
3. **Expected:** the "Update status" `<select>` dropdown shows options:
   - **Current: \<order status\>** (always disabled — that's the label, not an action)
   - **→ Confirm order (mark processing)** — enabled if order.status is `pending_payment` or `paid`
   - **→ Mark items shipped** — enabled if you have unfulfilled items AND order is not cancelled/refunded/failed/completed
   - **→ Mark delivered (fulfilled)** — enabled if order.status is `shipped`
4. Select an enabled option. Inertia submits. Flash message shows "success: ...".
5. Reload the page. **Expected:** the new status persists; the dropdown re-computes available options for the new state.
6. Continue through the lifecycle (confirm → ship → deliver).
7. Login as the customer who placed that order. Open the order page. **Expected:** they see the updated fulfillment status.

**Failure path:** if all options still appear grayed out:
```bash
grep -c "computeStatusOptions" app/Http/Controllers/Vendor/VendorOrderController.php
# Expected: 2 (1 method def + 1 call site)
grep -c "fulfillment_status === 'shipped'" resources/js/Pages/Vendor/Orders/Show.tsx
# Expected: 0 (the broken pre-v10.11 React-side rule must be gone)
```

If the dropdown is enabled but submission returns 403, that's a policy issue (vendor doesn't own the order's items) — not the v10.11 fix. The v10.11 fix is the dropdown availability.

### ☐ Confirmation C — §4 Support ticket reply works without lazy-load error

1. As **customer**: open an existing ticket at `/tickets/{id}`. Submit a reply. **Expected:** no exception; the new message appears with author name; "Reply posted" flash.
2. As **vendor**: open `/vendor/tickets/{id}`. Submit a reply. Same expectation.
3. As **admin (Filament)**: open `/admin/support-tickets/{id}` (the View page). Click "Reply". Submit. **Expected:** no `LazyLoadingViolationException`. Notification "Reply posted" appears. The Infolist shows your new message with author name.
4. Click "Change status" → set to Resolved. **Expected:** no lazy-load error; Infolist re-renders correctly.
5. Click "Change priority" → set to High. Same expectation.
6. Click "Assign" → set assignee. Same expectation.

**Failure path:** if you still see `Attempted to lazy load [user] on model [App\Models\SupportTicketMessage]`:
```bash
grep -c "messages.user:id,name,email" app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php
# Expected: 5 (1 in resolveRecord + 4 in mutating action callbacks)
```

### ☐ Confirmation D — §2 Subjective performance improvement

This is the hardest defect to verify objectively without instrumentation. Approximate proof:

1. Open browser dev tools → Network tab.
2. Login as admin.
3. Navigate between several admin/storefront pages.
4. **Expected:** each page renders without obvious multi-second delays. The Inertia response payload (look at `?_only=` requests if Inertia does partials) should be noticeably smaller because `auth.user.permissions` (an array of ~80 strings for an admin) is no longer in the default share.
5. The previously-shared `auth.user.permissions` is also not visible if you inspect the JSON of any page response.

**Regression guard:** `auth.user.is_admin` must STILL be present:
```bash
grep -c "'is_admin'" app/Http/Middleware/HandleInertiaRequests.php
# Expected: 1
```

If `is_admin` is missing, the admin React layouts will fail to show admin nav — that's a different bug.

## What to do if a confirmation fails

For each defect, the v10.11 CI sub-checks enforce the regression guard. If a confirmation fails:

1. **Confirm the deployed code is v10.11**: `cat VERSION` must say `Phase 10 v10.11`.
2. **Run the v10.11 Pest scenarios**: `php artisan test --filter=Phase10V1011`. If any of them fail, the deployed source is structurally wrong (not just runtime-wrong).
3. **For the SQL error specifically**: the dev's database might still be on an older schema. Run migrations: `php artisan migrate`. Then re-test.
4. **Hard refresh** the browser (Ctrl+Shift+R) to bypass cached `/build/assets/*.js` bundles. Confirm the loaded asset filenames match `public/build/manifest.json`.

## CI verdict

```
✅ Phase 10 v10.11 PASSES — ready for final deployment review
```

## Final

**Phase 10 v10.11 STOPS HERE.** No Phase 11. Pending dev runtime verification of confirmations A-D.
