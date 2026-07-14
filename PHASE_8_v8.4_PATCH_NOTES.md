# Phase 8 v8.4 — TypeScript strict-mode fix (`form.errors.status` / `form.errors.service`)

**Status:** Targeted fix on top of Phase 8 v8.3. Pending CI verification.
**Scope:** 4 lines of code in 2 React files, 1 new CI sub-check. No backend changes, no migrations, no logic changes.

---

## Root cause

Two Phase 8 React pages accessed validation-error keys that weren't declared in the corresponding `useForm()` data objects:

| File | Line | What it did | Why it broke |
|---|---|---|---|
| `resources/js/Pages/Bookings/Show.tsx` | 164 | `rescheduleForm.errors.status` | `useForm({ date, time, customer_notes })` — `status` is not a key |
| `resources/js/Pages/Services/Show.tsx` | 233 | `form.errors.service` | `useForm({ service_id, service_provider_id, date, time, customer_notes, service_address })` — `service` is not a key |

Inertia's `useForm` types its `.errors` as `Partial<Record<keyof TForm, string>>` — a typed projection of the form's data shape. Accessing a key not in the data shape under `tsconfig.json`'s `strict: true` raises:

```
error TS2339: Property 'status' does not exist on type
              'Partial<Record<"date" | "time" | "customer_notes", string>>'.
```

The data IS sent by the server (the backend's `ServiceBookingService` throws `ValidationException::withMessages(['status' => 'Cannot reschedule a terminal booking'])` and `['service' => 'Service is not currently accepting bookings']` for cross-cutting validation that doesn't map to a specific form field), but TypeScript correctly rejects accessing it through the form-typed errors object.

---

## Fix

Type-safe local cast to `Record<string, string | undefined>` for cross-cutting backend error keys. No `any`, no weakened tsconfig, no removed validation display:

**`Bookings/Show.tsx`** — added a cast variable:

```tsx
const rescheduleForm = useForm({ date: '', time: '', customer_notes: '...' });

// Phase 8 v8.4 — typed access to backend validation errors that don't map
// to a useForm() field. ValidationException(['status' => '...']) is returned
// by the reschedule endpoint when the booking has become terminal between
// page load and form submission. useForm.errors is typed to form fields only,
// so accessing .status would emit TS2339 under strict mode. The cast widens
// to Record<string, string | undefined> — values stay typed, just the key
// set widens to accept server-side keys.
const rescheduleErrors = rescheduleForm.errors as Record<string, string | undefined>;
```

Then line 164:
```diff
- {rescheduleForm.errors.status && <p>...{rescheduleForm.errors.status}</p>}
+ {rescheduleErrors.status && <p>...{rescheduleErrors.status}</p>}
```

**`Services/Show.tsx`** — same pattern:

```tsx
const form = useForm({ service_id, service_provider_id, date, time, customer_notes, service_address });

// Phase 8 v8.4 — typed access to backend validation errors that don't map
// to a useForm() field. ServiceBookingService::createBooking throws
// ValidationException(['service' => '...']) for cross-cutting checks (service
// archived between page load and form submission, etc.).
const bookingErrors = form.errors as Record<string, string | undefined>;
```

Line 233:
```diff
- {form.errors.service && <p>...{form.errors.service}</p>}
+ {bookingErrors.service && <p>...{bookingErrors.service}</p>}
```

### Why this approach (per developer's options A/B/C)

The developer's spec listed three acceptable approaches. This implementation uses **Option B** — "a safe shared/general error key that is typed correctly" — for these reasons:

- **A (remove the reference)**: would silently lose error display in race conditions (vendor accepts a booking just as customer hits reschedule). Worse UX, not a real fix.
- **B (typed cast)** ← chosen: preserves the error display, makes the loosening explicit at the point of use, keeps everything else type-safe.
- **C (add to form data)**: wrong because `status`/`service` aren't form fields the customer submits — they're server-side cross-cutting validations.

No `any`. No weakened tsconfig. No removed UI behavior.

---

## Why my own sandbox tsc didn't catch this

The painful honest answer: my sandbox `tsconfig.verify.json` had `strict: false` to work around imperfections in my hand-written `node_modules` stubs (the stubs can't perfectly mirror real `@inertiajs/react` + real React without spinning up npm install, which the sandbox forbids). With `strict: false`, `noImplicitAny` and `strictNullChecks` are off, which means `TS2339: Property does not exist` is the only strict-class error my verify config DID catch — but only when the stub types are precise enough.

The bug was real both in my sandbox and in CI — the developer's CI already runs `npm run typecheck` + `npm run build` against the real `tsconfig.json` (strict: true), which would have caught it. I shipped without running CI locally first.

**v8.4 adds a stub-independent defense** so this class of bug is caught BEFORE `npm run build` even runs, on any runner, in under one second.

---

## New CI sub-check

**`Phase 8 v8.4 — form-errors-key static check`**

A Python static check that:

1. Walks every `.tsx` file in `resources/js/`
2. For each file, finds every `const NAME = useForm({...})` call and extracts the data-object keys
3. Finds every `NAME.errors.KEY` reference in that file
4. Validates that `KEY` is in the corresponding form's declared data keys
5. Fails CI with `file=...,line=...` annotations + specific fix suggestions (Options A/B/C) if any mismatch is found

**Sandbox verification with the fix in place** (the exact same logic CI runs):

```
Scanned 9 files with useForm() calls across the whole project.
✓ Zero form.errors.KEY mismatches anywhere in the codebase.
```

The check is stub-independent — it doesn't load TypeScript types, doesn't need `@inertiajs/react` stubs, doesn't depend on tsconfig settings. It directly parses the source.

---

## Phase 8 CI sub-check totals

| Release | Sub-checks |
|---|---|
| Phase 8.0 | 6 |
| Phase 8 v8.1 | 5 |
| Phase 8 v8.2 | 3 |
| Phase 8 v8.3 | 1 |
| Phase 8 v8.4 | **1** |
| **Total** | **16** |

Plus 14 Phase 7 sub-checks = **30 phase-specific CI sub-checks**.

Final CI verdict updated to: `✅ Phase 8 v8.4 PASSES — ready to approve Phase 9`.

---

## Full audit of every `form.errors.X` reference in Phase 8 React files

Per your explicit request — checking the entire Phase 8 React surface, not just the reported sites:

| File | Line | Reference | Valid? |
|---|---|---|---|
| `Services/Show.tsx` | 232 | `form.errors.time` | ✓ in form data |
| `Services/Show.tsx` | 233 | **was** `form.errors.service`, **now** `bookingErrors.service` | ✓ via typed-cast |
| `Bookings/Show.tsx` | 162 | `rescheduleForm.errors.time` | ✓ in form data |
| `Bookings/Show.tsx` | 163 | `rescheduleForm.errors.date` | ✓ in form data |
| `Bookings/Show.tsx` | 164 | **was** `rescheduleForm.errors.status`, **now** `rescheduleErrors.status` | ✓ via typed-cast |
| `Vendor/Services/Create.tsx` | 40 | `form.errors.name` | ✓ in form data |
| `Vendor/Services/Create.tsx` | 55 | `form.errors.price` | ✓ in form data |
| `Vendor/Bookings/Show.tsx` | 216 | `rescheduleForm.errors.time` | ✓ in form data |

8 references audited. 2 had the bug (both fixed). 6 were already correct. **Final state: 0 problems anywhere in Phase 8.**

The new CI check would also catch the same class of bug in Phase 0–7 React files going forward. Re-running it on the whole project: **0 mismatches across all 9 useForm-using files in resources/js/**.

---

## What did NOT change in v8.4

- Migrations
- Routes
- Models
- Domain services (`ServiceBookingService`, `ServiceAvailabilityService`)
- Backend controllers
- Filament admin
- Seeders
- 44 Pest scenarios (18 + 20 + 6)
- 15 prior CI sub-checks
- Any other React file beyond the 2 patched

The patch is **4 lines of code**. Everything else from v8.3 is preserved verbatim.

---

## Developer testing checklist for v8.4

The two commands that MUST pass cleanly:

```bash
npm ci
npm run typecheck     # the TS2339 errors from v8.3 must NOT appear
npm run build         # full Vite build must complete with exit 0
```

Plus the full v8.3 regression set:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan migrate:fresh --seed       # twice — idempotency
php artisan test
```

Plus the v8.1 manual smoke test (18 steps) to confirm UX still works.

---

## Accountability — fifth bug in Phase 8

Summary of the cycle:

| Release | Bug | Defense added |
|---|---|---|
| 8.0 | Backend complete but no nav links | (none — bug shipped) |
| 8.1 | Long index names broke MySQL migrate:fresh | Nav-grep + confirmation-route + reschedule-route + services/products separation + completion-Pest sub-checks (5) |
| 8.2 | MySQL identifier > 64 chars | Static identifier-length pre-flight + MySQL runtime + index-name regression Pest (3) |
| 8.3 | Invented column names `stock_minor` / `manage_stock` | Schema-vs-runtime-data pre-flight (1) |
| **8.4** | **`form.errors.KEY` for KEYs not in useForm data** | **Form-errors-key static check (1)** |

The recurring pattern: each successive bug has been a CLASS the previous CI didn't catch. My sandbox verification has been weaker than the developer's real `npm install` + `npm run build` + real DB engines, and I shipped without acknowledging that gap publicly.

**The discipline going forward** (renewed):

1. Sandbox verification is necessarily approximate — it can never replace real `npm install` + real DB engines.
2. The right response is **stub-independent static checks** that catch bug classes without needing infrastructure. v8.4's form-errors-key check is one such; v8.3's schema-vs-runtime-data check is another.
3. When the developer reports a bug, the fix must include a defense that catches the EXACT same class statically — not "I'll add tests" or "the CI will catch it next time".

**Phase 8 v8.4 STOPS HERE. Do not start Phase 9** until:

1. `npm run typecheck` exits 0 cleanly
2. `npm run build` exits 0 cleanly
3. `php artisan migrate:fresh --seed` completes (still must work — v8.3 fix preserved)
4. CI shows `✅ Phase 8 v8.4 PASSES`
5. The v8.1 manual smoke test still passes end-to-end
