# Phase 2 — v3.2 Patch Notes

**Type:** Functional fix package (no scope additions).
**Built on:** Phase 2 v3.1 (CSS/JS now load — see PHASE_2_v3.1_PATCH_NOTES.md).
**Status:** Drop-in over v3.1.

---

## What was broken in v3.1

Visually the app was fine after v3.1, but **functionally** four workflows were broken:

1. **Admin login didn't go to the Filament panel.** After successful login, the user stayed on `/login`, staring at the Inertia login page (which they reasonably interpreted as "a minimal dashboard").
2. **`/vendor/apply` appeared to redirect back to `/login`** even for logged-in users.
3. **No prominent "Become a Vendor" entry point** outside `Welcome.tsx`'s logged-in-customer view.
4. **Arabic / Urdu language buttons** changed the URL (`?locale=ar`) but didn't actually translate anything — no `App::setLocale()`, no translation files.

---

## Root causes (verified by reading the code, not guessing)

### Bug A — Inertia can't follow cross-app redirects (the headline)

`LoginController::store()` returned `redirect()->intended('/admin')` for admins. That's a standard 302 redirect. But:

1. The Inertia client made the `POST /login` with `X-Inertia` header.
2. It receives the 302 and follows it: `GET /admin` **with `X-Inertia` header**.
3. Filament's `/admin` is **not** an Inertia route — it returns plain HTML, no `X-Inertia` header in the response.
4. Inertia 2's client refuses to render a non-Inertia response in this context. The user is stranded on `/login`.

This single bug explains **issues #1, #2, and #4** in the bug list.

### Bug B — Language switcher was a UX lie

`LangSwitcher.tsx` just put `?locale=ar` in the URL. No middleware ever read it. No `lang/` translation files existed. Users clicked, the page reloaded, nothing changed. We **delete** the switcher in v3.2 rather than ship a button that doesn't work; full i18n (translations + RTL + the `SetLocale` middleware) is its own focused work item for a later phase.

### Issue C — Single CTA entry point

"Become a vendor" lived only on `Welcome.tsx`, only for logged-in non-admins. Guests and visitors on Login/Register/storefront pages had no way to start the flow.

---

## The fix

### `app/Http/Controllers/Auth/LoginController.php`
- Added `redirectAfterLogin(Request, User): HttpResponse`. It:
  1. Resolves the destination: stored `url.intended`, or default per role.
  2. **If the destination is outside the Inertia SPA *and* the request came from Inertia**, returns `Inertia::location($url)` (HTTP 409 + `X-Inertia-Location` header). The Inertia client treats this as a hard-navigation signal and does `window.location = url` — a clean browser page load into Filament.
  3. Otherwise, plain `redirect($url)` (still gets normal session flash behaviour).
- Per-role defaults:
  - `super_admin` / `admin_staff` → `/admin`
  - `vendor` (any status) or user with a Vendor record → `/vendor`
  - everyone else → `/`
- `isExternalToInertia($url)` checks for `/admin` and `/api` path prefixes — easily extendable.

### `app/Http/Controllers/Auth/RegisterController.php`
- Same `Inertia::location` defence (in case an admin is ever bulk-registered and lands at `/admin`).
- Honours the session's `url.intended` so registering via the "Become a vendor → Register" flow lands the new user on `/vendor/apply`.
- Calls `session()->regenerate()` after `Auth::login()` (was missing — important for session-fixation hardening).

### `resources/js/Pages/Welcome.tsx`
- **Guests** now see a **"Become a vendor"** outline button next to Sign in / Register.
- Behaviour:
  - Guest clicks → `/vendor/apply` → auth middleware → `/login` (intended stored) → after login → `/vendor/apply`.
  - Customer clicks → goes directly to `/vendor/apply`.
  - Vendor sees "Vendor dashboard" instead.
  - Admin sees the Admin button (no vendor CTA — admins don't sell).

### `resources/js/Pages/Auth/Login.tsx`, `resources/js/Pages/Auth/Register.tsx`
- Added a subtle footer link under each form: "Want to sell on the marketplace? **Become a vendor →**". Same `/vendor/apply` target.

### `resources/js/Layouts/StorefrontLayout.tsx`
- Header now shows a primary **"Become a vendor"** button on every storefront page (homepage, future product / vendor pages). Adapts label to "Vendor dashboard" when the viewer is already an approved vendor.
- **Removed `<LangSwitcher />`.** The `Components/common/LangSwitcher.tsx` file is **deleted** (not just hidden) so it can't accidentally be re-imported.
- Added a real account widget: logged-in name + Admin link (if admin) + Logout; or Sign in / Register for guests.

### `resources/js/Components/common/LangSwitcher.tsx`
- **Deleted.** Will be rebuilt in a later phase that also ships:
  - `SetLocale` middleware reading session/cookie locale
  - `lang/{en,ar,ur}/*.json` translation files
  - Translations shared via Inertia props
  - A small client-side `t()` helper
- Until then, the marketplace runs English-only. `app.locale` and `app.direction` are still surfaced in shared props for forward-compat.

---

## New tests

| File | What it locks in |
|---|---|
| `tests/Feature/LoginRedirectTest.php` *(new — 9 tests)* | super_admin → `/admin`; admin_staff → `/admin`; approved vendor → `/vendor`; customer → `/`; intended URL preserved; Inertia admin login returns 409 + `X-Inertia-Location: …/admin`; Inertia customer login returns 302; suspended account refused |
| `tests/Feature/VendorApplyAccessTest.php` *(new — 11 tests)* | Guest → `/vendor/apply` redirects to `/login` with intended URL; logged-in customer sees the form; existing vendor bounced to `/vendor`; **full guest→login→`/vendor/apply` cycle**; "Become a vendor" link visible on `/`, `/login`, `/register`; "Vendor dashboard" link visible to existing vendors; LangSwitcher absent; pending vendor created on application submit |

Combined with the existing tests, v3.2 covers all 9 manual-check items in the spec.

---

## CI updates

- Workflow renamed: `Phase 2 Verification` → `Phase 2 v3.2 Verification`.
- Final verdict line: **`✅ Phase 2 v3.2 PASSES — ready to approve Phase 3`**.
- All existing v3.1 asset-verification steps (Filament assets published, Vite manifest exists, manifest has required entries) **kept**.
- The new tests above run inside the existing `php artisan test` step — no separate steps needed.

---

## Manual verification checklist

After deploying v3.2, walk this list. Every line should pass.

| # | Action | Expected |
|---|---|---|
| 1 | Visit `/` while logged out | "Become a vendor" + Sign in + Register all visible |
| 2 | Visit `/login` | "Become a vendor →" link below the form |
| 3 | Visit `/register` | "Become a vendor →" link below the form |
| 4 | Click "Become a vendor" as a guest | Redirected to `/login`; URL bar shows `/login` |
| 5 | Register a fresh account at `/register` | Redirected to `/vendor/apply` (because of step 4's intended URL) |
| 6 | Submit the vendor form | Lands on `/vendor` with pending status banner |
| 7 | Log out, log in as `admin@marketplace.test` / `password` | **Lands on `/admin`**, full Filament sidebar visible, navigation groups: Marketplace / Access Control / Configuration |
| 8 | In Filament, sidebar → Marketplace → Vendors | Pending application from step 6 is in the list with status badge |
| 9 | Click row → Approve → pick a package → confirm | "Vendor approved" toast; subscription + commission rule auto-created |
| 10 | Log out, log back in as the test vendor | **Lands on `/vendor`**, dashboard shows approved status |
| 11 | Open public `/vendors/{slug}` | Renders the public storefront placeholder (200 OK) |
| 12 | View any storefront page | **No** Arabic / Urdu language buttons anywhere |

If step 7 lands you back at `/login` instead of `/admin`, that's the v3.1 Inertia-cross-app bug — confirm you actually deployed v3.2 (look for `Inertia::location` in `app/Http/Controllers/Auth/LoginController.php`).

---

## Known limitations (still intentional)

| Item | Phase |
|---|---|
| Real i18n: `SetLocale` middleware + lang files + RTL toggle UI | Later (own focused work item) |
| Customer account dashboard (`/account` or similar) | Phase 3+ (currently customers land on `/`) |
| Re-apply flow for rejected vendors (currently they see the reason on `/vendor` but can't reset the application themselves) | Phase 9 (admin can manually `reopen` already) |
| Payment processing for paid vendor packages | Phase 4 |

---

## Stop discipline

**Phase 3 is still untouched.** Reply **"approve Phase 3"** only after the manual checklist above passes against v3.2 with a green CI.
