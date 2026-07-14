# Phase 10 v10.16 — Blank Homepage Runtime Repair

Per dev §14.

## Critical context

The dev reported a launch-blocking frontend regression: `GET /` renders a completely blank page in the browser. Initially observed after customer login; subsequently affecting guest sessions as well. Backend response = HTTP 200 (Inertia JSON valid). Route → `HomeController::index` → renders `Welcome.tsx`. Therefore: **React runtime exception during render**, not a Laravel server error.

## Exact root cause

`resources/js/Pages/Welcome.tsx` (pre-v10.16) contained:

```tsx
{user.permissions.length > 0 && (
    <span> · {user.permissions.length} permission(s) granted</span>
)}
```

The dev's own pre-existing optimization in **v10.11 §2** removed `permissions` from the default Inertia share for performance (was ~80-row Spatie `getAllPermissions()->pluck('name')->toArray()` per render). Post-v10.11, `auth.user.permissions` is `undefined` for authenticated users.

Accessing `.length` on `undefined` throws `TypeError: Cannot read properties of undefined (reading 'length')` at React render time. Result:
1. React unmounts the entire tree.
2. The DOM is left blank.
3. Browser DevTools Console shows the error.
4. Backend still returns HTTP 200 — the failure is purely client-side.

This is the exact failure mode the dev's §3 named.

### Why it appeared after customer login (and only sometimes for guests)

- **Authenticated customer**: `auth.user` is truthy → enters the `{user && (...)}` block → tries `user.permissions.length` → **crash**.
- **Guest**: `auth.user` is `null` → `{user && (...)}` short-circuits → block never renders → **no crash**, page renders.

So pre-v10.16: guests = OK, customer login = blank.

After v10.15's defensive wrapping (which can return `null` for `auth.user` under failure conditions), the behavior shifted: customers consistently crash, and edge-case guest renders could also expose the same bug (if `auth.user` was accidentally non-null with malformed shape). The dev's report of "later changes" causing guest blanks aligns with this.

### Secondary issue: stale TypeScript contract

`resources/js/types/inertia.d.ts:20` declared:

```ts
permissions: string[];   // REQUIRED
```

But the backend stopped sending it in v10.11 §2. The frontend type was a lie. TypeScript wouldn't flag the unsafe access because the type promised the field existed.

## Exact files changed (smallest safe correction)

| File | Change |
|---|---|
| `resources/js/Pages/Welcome.tsx` | Replaced `user.permissions.length` with the safe pattern `const permissions = user.permissions ?? []; permissions.length`. The badge still renders if a user happens to have direct permissions (e.g. via an admin user who lands on `/`), but never crashes when `permissions` is undefined. The visible behavior is identical for users with no direct permissions (the badge stays hidden), which is the overwhelming common case. |
| `resources/js/types/inertia.d.ts` | `permissions: string[]` → `permissions?: string[]`. JSDoc comment added explaining v10.11 §2 reasoning so future devs don't re-mark it required. |
| `tests/Feature/Phase10V1016RegressionTest.php` | NEW — 20 Pest scenarios per dev §11 |
| `.github/workflows/ci.yml` | 3 new v10.16 sub-checks: unsafe pattern absent from Welcome.tsx; AuthUser.permissions marked optional; Pest v10.16 filter |
| `VERSION` | `Phase 10 v10.15` → `Phase 10 v10.16` |

**Note**: I did NOT re-add the global `permissions` array to the Inertia share. Per dev §9: "The performance optimization intentionally removed globally loaded permissions. Preserve that optimization unless permissions are genuinely required throughout the whole storefront." Welcome.tsx's permission count was diagnostic UI, not functional — the fix is to make the UI safe, not to bring back the 80-row pluck on every request.

## Exact browser console error (anticipated)

```
TypeError: Cannot read properties of undefined (reading 'length')
    at Welcome (Welcome.tsx:123)
    at renderWithHooks (react-dom-client.development.js:...)
    at updateFunctionComponent (...)
```

Stack trace points at `resources/js/Pages/Welcome.tsx` line 123 (the `user.permissions.length > 0` expression). After v10.16, this line no longer exists — replaced with `permissions.length > 0` where `permissions` is `user.permissions ?? []`.

## Actual backend `auth.user` structure (current contract)

From `app/Http/Middleware/HandleInertiaRequests.php` (v10.15 defensive-wrapped `auth.user` closure):

```php
return [
    'id'              => $u->id,
    'name'            => $u->name,
    'email'           => $u->email,
    'email_verified'  => $u->hasVerifiedEmail(),
    'roles'           => $u->getRoleNames()->toArray(),
    'is_admin'        => $u->hasAnyRole(['super_admin', 'admin_staff']),
    'vendor_status'   => $u->vendor?->status,
];
// NOTE: no `permissions` key — removed v10.11 §2 for performance.
```

## Stale frontend type definition

Pre-v10.16:
```ts
export interface AuthUser {
    ...
    permissions: string[];   // CLAIMED required; backend never sent it
    ...
}
```

Post-v10.16:
```ts
export interface AuthUser {
    ...
    /** v10.11 §2 perf — no longer in default share. Read defensively. */
    permissions?: string[];
    ...
}
```

## Why permissions were not restored globally

Per dev §9: backend authorization is unaffected by removing the client-side `permissions` array. Routes are protected by middleware, controllers use Spatie's `authorize()`, Filament resources use policies. The `permissions` array on the Inertia share was only ever a client-side display hint. Restoring the 80-row pluck per render to satisfy one display widget on `/` is the wrong trade-off — the dev explicitly identified this trade in v10.11 §2 and committed to it.

If any specific page genuinely needs the permission list, the correct pattern is a per-page partial reload, e.g.:

```php
return Inertia::render('SomeAdminPage', [
    'permissions' => Inertia::lazy(fn () => $request->user()->getAllPermissions()->pluck('name')),
]);
```

…rather than re-adding it to the global share.

## Audit summary (per dev §6 + §7 + §8)

I searched the entire frontend for similar permission-assumption bugs:

```bash
grep -rnE "permissions\.length|permissions\.map|permissions\.filter" resources/js/
```

After v10.16: **0 unsafe occurrences** (only the safe `permissions.length` AFTER the `?? []` default).

Audited `StorefrontLayout.tsx` per dev §7:
- `user.roles.includes('vendor')` — guarded by `!user ? ... : user.roles...` (user is non-null at access time). Safe.
- `cart_summary && cart_summary.items_count` — null-safe. Safe.
- `top_categories.length` / `.map(...)` — type is `Array<{slug, name}>`, v10.15 share() returns `[]` from catch handler. Safe.
- `app.name`, `marketplace.*` — required props, always present. Safe.

No other components needed changes.

## Automated test results (static)

20 Pest scenarios in `Phase10V1016RegressionTest.php`. Maps to the dev's §11:

| § | Pest scenario |
|---|---|
| 11.1 | GET / as guest → 200 + Welcome |
| 11.2 | GET / as authenticated customer → 200 + Welcome |
| 11.3 | Welcome Inertia component renders |
| 11.4 | auth.user contract has NO permissions key (v10.11 §2 preserved) |
| 11.5 | Customer without permissions still gets HTTP 200 |
| 11.6 | Guest with auth.user = null still gets HTTP 200 |
| 11.7 | cart_summary null for guests; top_categories always an array |
| 11.8 | POST /login → 302 / → GET / returns 200 (end-to-end customer flow) |
| 11.9 | Vendor home (/vendor) returns 200 |
| 11.10 | Admin reports surface (/admin/reports) returns 200 |
| §3 + §8 | Welcome.tsx has zero `user.permissions.<method>` direct accesses (regex check) |
| §3 + §8 | Welcome.tsx has the safe `user.permissions ?? []` normalization |
| §3 | Welcome.tsx has the v10.16 §4 marker |
| 11.11 + §5 | AuthUser.permissions is optional in inertia.d.ts |
| 11.12 + §9 | v10.11 §2 perf preserved (no getAllPermissions in share) |
| 11.12 | v10.15 defensive wrappings preserved (all 5 markers + HomeController) |
| 11.12 | v10.14 scope-aware closures preserved (2 admin/, 2 vendor/) |
| 11.12 | v10.14 indexes migration preserved |
| Cross-cut | VERSION = Phase 10 v10.16 |
| Cross-cut | v10.0-v10.15 preservation: every prior fix marker intact |

CI sub-check totals: Phase 10 = **65**.

## Manual customer login redirect result

I cannot perform browser verification from this sandbox. The dev's §12 walkthrough is the acceptance gate. After deploying v10.16, the dev should:

1. `php artisan optimize:clear && npm run build`
2. Restart PHP-FPM (or artisan serve)
3. Hard-refresh the browser (Ctrl+Shift+R)
4. GET `/` as guest → page renders, no Console error
5. Login as customer → redirected to `/` → page renders, no Console error
6. Refresh `/` → still rendered, customer still logged in
7. Vendor login → `/vendor` still works
8. Admin (Filament) login → `/admin/login` flow still works

## Confirmation that no Console error remains

Static guarantee: the only `user.permissions.length` access in `resources/js/` was at Welcome.tsx line 123 (verified by `grep -rn "user\.permissions\.length" resources/js/` — 0 hits after v10.16). The TypeScript type now declares `permissions?: string[]` matching the runtime contract. Any future page that does `user.permissions.length` directly will get a TypeScript error pointing at the unsafe access.

## Confirmation that performance optimizations remain intact

| Optimization | v10.16 status |
|---|---|
| v10.1 perf indexes migration | ✓ untouched |
| v10.11 §2 `permissions` removed from default share | ✓ STILL REMOVED (this is the point — we fixed the frontend to match) |
| v10.11 §3 vendor order computeStatusOptions | ✓ untouched |
| v10.11 §4 Filament eager-loads | ✓ untouched |
| v10.11 §5 SUM(requested_amount_minor) | ✓ untouched |
| v10.12 Spatie scope on customers_total | ✓ untouched |
| v10.13 vendor reports navigation | ✓ untouched |
| v10.14 scope-aware Inertia closures | ✓ untouched |
| v10.14 homepage health cache | ✓ untouched |
| v10.14 8 composite indexes | ✓ untouched |
| v10.15 defensive try/catch wrappings | ✓ untouched |

**Zero performance work was reverted.** v10.16 is purely a frontend contract alignment.

## Per dev §16 acceptance

This phase fixed the SPECIFIC blank-homepage runtime bug identified by the dev. The fix touches 2 frontend files (one .tsx, one .d.ts). Pending the dev's §12 browser walkthrough.
