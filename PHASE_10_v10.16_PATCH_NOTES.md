# Phase 10 v10.16 — Patch Notes

## What's fixed

| Defect | Root cause | Fix |
|---|---|---|
| Homepage `/` renders blank in browser. Initially observed post-customer-login, then also for guests after v10.15 made share() fail-safe. Backend returns HTTP 200; failure is purely client-side React render exception. | `resources/js/Pages/Welcome.tsx` did `user.permissions.length > 0` and `{user.permissions.length} permission(s)` — but v10.11 §2 had removed `permissions` from the default Inertia share for performance (~80-row Spatie pluck per request). `user.permissions` is `undefined` for all authenticated users → `.length` on undefined → `TypeError` at React render → React unmounts the entire tree → blank DOM. Secondary issue: `resources/js/types/inertia.d.ts` declared `permissions: string[]` as REQUIRED, masking the unsafe access from TypeScript. | **Welcome.tsx**: read via `const permissions = user.permissions ?? []` and use `permissions.length` thereafter. Smallest safe change per dev §4. **inertia.d.ts**: `permissions?: string[]` (optional) — matches the actual backend contract. |

## Counts

| | v10.15 → v10.16 |
|---|---|
| Phase 10 CI sub-checks | 62 → 65 |
| Phase 10 Pest scenarios | 224 → 244 |
| Files modified | 2 (Welcome.tsx + inertia.d.ts) |
| New files | 1 (Pest test) |
| PHP files modified | 0 |
| Routes / config / auth files | 0 |
| v1-v9 files touched | 0 |
| v10.0-v10.15 fix code reverted | 0 |
| Performance work reverted | 0 |
| Helpers added | 3 (`p1016_seed`, `p1016_customer`, `p1016_vendor_user`, `p1016_admin`) — 89 total unique, 0 duplicates |

## Why the global permissions array was NOT restored

Per dev §9: "Preserve [the v10.11 §2 optimization] unless permissions are genuinely required throughout the whole storefront. Frontend visibility is not a substitute for backend authorization."

The pre-v10.16 use case for `auth.user.permissions` was a debug/diagnostic UI element on `Welcome.tsx` ("· N permission(s) granted"). That's not a functional requirement. Backend authorization (Spatie middleware + Laravel policies + Filament resource access control) is entirely independent of what the client-side share contains.

If a future page needs the permission list, the correct pattern is `Inertia::lazy()` on a per-page basis, not re-adding the ~80-row pluck to the global share.

## v10.0-v10.15 preservation

| Optimization / fix | Preserved? |
|---|---|
| v10.1 7 performance indexes migration | ✓ |
| v10.2 VERSION + Inertia share marketplace:version cache | ✓ |
| v10.3-v10.7 hamburger menu + locale/SEO fixes | ✓ |
| v10.8 promotion snapshot column + index | ✓ |
| v10.9 mobile catalog fixes | ✓ |
| v10.10 admin reports direct guard + commands | ✓ |
| v10.11 §2 permissions removed from share | ✓ (the whole point — v10.16 makes frontend match) |
| v10.11 §3 vendor order computeStatusOptions | ✓ |
| v10.11 §4 Filament eager-loads (5) | ✓ |
| v10.11 §5 SUM(requested_amount_minor) | ✓ |
| v10.12 customers_total Spatie scope | ✓ |
| v10.13 vendor reports navigation | ✓ |
| v10.14 scope-aware Inertia closures + 30s health cache + 8 composite indexes | ✓ |
| v10.15 defensive try/catch on every share closure + HomeController | ✓ |

## Backend auth.user contract (confirmed current shape)

```php
// HandleInertiaRequests.php
'user' => function () use ($request) {
    try {
        $u = $request->user();
        if (! $u) return null;
        return [
            'id'              => $u->id,
            'name'            => $u->name,
            'email'           => $u->email,
            'email_verified'  => $u->hasVerifiedEmail(),
            'roles'           => $u->getRoleNames()->toArray(),
            'is_admin'        => $u->hasAnyRole(['super_admin', 'admin_staff']),
            'vendor_status'   => $u->vendor?->status,
            // NOTE: NO `permissions` field — removed v10.11 §2 for performance.
        ];
    } catch (\Throwable $e) { ... }
}
```

Frontend `AuthUser` type now matches this shape exactly.

## Per dev §16 acceptance

The dev runs:
```bash
php artisan optimize:clear
php artisan route:list --path=/
php artisan test --filter=Phase10V1016    # → 20 scenarios
php artisan test
npm run typecheck                          # MUST PASS (no @ts-ignore added)
npm run build
```

Then performs the §12 manual walkthrough (guest /, customer login → /, refresh, logout, vendor /vendor, admin /admin/login).

**Phase 10 v10.16 STOPS HERE.** No Phase 11. Customer homepage rendering is the launch-blocking gate; v10.16 fixes the exact crash the dev identified.
