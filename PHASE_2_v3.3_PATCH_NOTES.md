# Phase 2 — v3.3 Emergency Fix

**Type:** Foundation stability fix. No new scope.
**Built on:** Phase 2 v3.2 (which made things worse — this rolls back its biggest mistakes and ships the real fixes).
**Status:** Drop-in replacement. Apply over your v3.2 checkout.

---

## TL;DR — what was actually broken

Five separate bugs, but **one of them caused most of the visible damage**: the CSRF token in `bootstrap.ts` was pinned to a stale meta tag at app boot. Laravel rotates the token on every login. After login, every POST sent the stale token → **419 Page Expired** → user appeared "logged out during navigation" → "Become a vendor" appeared to "redirect back to /login". Fix this one bug, three of your six reported symptoms disappear.

The other four bugs:

| # | Bug | Symptom |
|---|---|---|
| 2 | `/admin/login` unstyled | v3.1 entrypoint check `if [ ! -d public/css/filament ]` passed when the dir existed with stale assets from an older build, so it never re-published |
| 3 | Admins could (and did) use `/login` | No role-gate on the public Inertia login — admins ended up authenticated through the wrong flow |
| 4 | Language buttons removed without a replacement | v3.2's "Option B" left the marketplace English-only with no path forward |
| 5 | `.env.example` missing session/cookie defaults | Class-of-bug: any cookie domain / SameSite / Secure mismatch in production would re-introduce 419s |

---

## What v3.3 changes — every file, with the reason

### Headline CSRF fix

**`resources/js/bootstrap.ts`** — switched from stale meta-token pinning to Axios's built-in XSRF-cookie reader:

```ts
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName  = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName  = 'X-XSRF-TOKEN';
```

Axios now reads the `XSRF-TOKEN` cookie **on every request** and forwards it as `X-XSRF-TOKEN`. Laravel's `VerifyCsrfToken` middleware decrypts that cookie value, compares it to the session token, and accepts it. When the session regenerates (on login, on logout, periodically), Laravel updates the cookie and Axios picks it up automatically. **No more stale-token 419s.**

### Auth flow separation

**`app/Http/Controllers/Auth/LoginController.php`** — fully rewritten:
- `/login` now **rejects** super_admin and admin_staff with a clear `ValidationException`:
  > "Admin users must sign in via /admin/login."
- After rejection, the session is fully torn down (`Auth::logout()` + `invalidate()` + `regenerateToken()`) so no half-authenticated state lingers.
- Three-tier destination resolution: `session('url.intended')` → whitelisted `?redirect=` query param → role default.
- `?redirect=` whitelist: `/vendor/apply`, `/vendor`, `/account` only — closes the open-redirect risk.
- Default destinations: vendor (any status) → `/vendor`; everyone else → `/`. Admins removed entirely (they can't reach this endpoint).
- **Removed the entire `Inertia::location()` branch** that v3.1/v3.2 needed for cross-app redirects. With admins blocked at the door, the cross-app redirect path no longer exists.

**`app/Http/Controllers/Auth/RegisterController.php`** — simplified to match v3.3:
- Same `?redirect=` whitelist as LoginController.
- Removed the dead `Inertia::location()` branch.
- New users still always get the `customer` role; admin provisioning stays manual via Filament.
- `?intended=vendor` flow now works end-to-end: the Welcome page's guest "Become a vendor" button goes to `/login?redirect=/vendor/apply`; the Register page has a matching link to `/register?redirect=/vendor/apply`; both flows land the new user on `/vendor/apply` immediately after auth.

### Filament asset reliability

**`docker/entrypoint.sh`** — `php artisan filament:upgrade` runs **unconditionally** at every container start:

```bash
echo "▶ Publishing Filament assets (always, idempotent)…"
php artisan filament:upgrade --ansi
```

v3.1 only ran it when `public/css/filament/` was missing. That check passed for users who upgraded Filament without rebuilding the Docker image — the directory existed with stale assets from the older version, so the styling looked broken. The command is idempotent and takes ~1 second; running it always is the right trade-off.

### Session / CSRF environment defaults

**`.env.example`** — added an explicit session block tuned for the Docker dev setup:

```ini
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
SESSION_PATH=/
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000
```

The comment block documents the production overrides (HTTPS + real domain → `SESSION_SECURE_COOKIE=true` + `SESSION_DOMAIN=.your-domain.tld`). These defaults prevent the most common class of 419: a cookie the browser refuses to send back because of a domain/secure/samesite mismatch.

Also updated `SUPPORTED_LOCALES=en,ar,ur` (was `en,ar`) and the matching default in `config/marketplace.php` — Urdu translations are now real, not placeholder.

### i18n — Option A, done properly

**`app/Http/Middleware/SetLocale.php`** *(new)* — runs in the `web` middleware group **before** `HandleInertiaRequests`. Resolves the active locale from (in priority): authenticated user's `locale` column → session `locale` → `APP_LOCALE`. Only honors values in `config('marketplace.supported_locales')` (anti-injection).

**`app/Http/Controllers/LocaleController.php`** *(new)* — `POST /locale/{code}` writes the choice to the session and (when logged in) persists it to `users.locale`. Unsupported codes return 404.

**`routes/web.php`** — registers `Route::post('/locale/{code}', …)->whereIn('code', ['en','ar','ur'])`.

**`bootstrap/app.php`** — `SetLocale` registered in the web group, before `HandleInertiaRequests`, so the shared `translations` prop reflects the active locale.

**`app/Http/Middleware/HandleInertiaRequests.php`** — now shares a flat `translations: Record<string,string>` map (English merged underneath the active locale as a fallback) with every page. The old `csrf_token` shared prop is **gone** — it was a footgun (developers reading a value that could be stale by the time React rendered). Inertia POSTs use the live cookie.

**`lang/en.json`, `lang/ar.json`, `lang/ur.json`** — each has the same 62 keys covering navigation, auth pages, vendor application, common buttons. Arabic and Urdu are real translations, not placeholders.

**`resources/js/lib/i18n.ts`** *(new)* — `useT()` React hook. Returns `(key, vars?) => string`. Supports `{name}` interpolation, falls back to the key itself if a translation is missing in both the active locale and English (loud failure, not silent).

**`resources/js/Components/common/LangSwitcher.tsx`** *(re-created)* — POSTs to `/locale/{code}` via Inertia's `router.post(…, { preserveState: false })`, forcing a fresh prop reload so translations swap immediately. The `<html dir="…">` flips to RTL for `ar` and `ur` automatically (the Blade root view reads `app()->getLocale()`).

**`resources/js/Layouts/StorefrontLayout.tsx`** — re-introduces `<LangSwitcher />` in the header. Every nav label now goes through `t('…')`. Per-role "Become a vendor" / "Vendor dashboard" CTA preserved from v3.2.

**`resources/js/Pages/Auth/Login.tsx`** and **`Auth/Register.tsx`** — every visible label translated. Each page accepts a `redirect` prop, preserves it through the POST as a hidden form field, and shows an admin-redirect hint at the bottom of the login page.

### Types

**`resources/js/types/inertia.d.ts`** — `SharedProps` now has `translations: Record<string,string>` and no longer has `csrf_token`.

---

## Tests — what's new in v3.3

`tests/Feature/AuthSeparationAndCsrfTest.php` *(new — 15 scenarios)*:

| Scenario | Locks in |
|---|---|
| `super_admin` rejected at `/login` | Admin separation |
| `admin_staff` rejected at `/login` | Admin separation |
| Customers still login via `/login` | Public flow not broken |
| Approved vendor still lands on `/vendor` | Role-based redirect |
| `/admin/login` is Filament HTML (no X-Inertia header) | Routing separation |
| `?redirect=/vendor/apply` honored | Vendor signup loop |
| `?redirect=https://evil.example.com` ignored | Open-redirect defense |
| Customer `POST /login` does NOT return 419 | CSRF flow works |
| `POST /logout` does NOT return 419 | CSRF flow works |
| Vendor application submit does NOT return 419 | CSRF flow works |
| Session persists across two consecutive page loads | No spurious logout |
| `POST /locale/ar` switches the active locale | Locale switching |
| Unsupported locale code returns 404 | Locale validation |
| Locale persists to `users.locale` when authenticated | Cross-session locale |
| Arabic translation map is delivered through Inertia props | i18n end-to-end |

`tests/Feature/LoginRedirectTest.php` *(rewritten)* — removed the admin-on-`/login` tests (they're now in AuthSeparationAndCsrfTest with the inverted assertion), kept and tightened: vendor → `/vendor`, customer → `/`, intended URL honored, pending-vendor-with-customer-role → `/vendor` edge case, suspended/banned still rejected.

**Test totals:** 113 scenarios across 19 files (up from ~94 in v3.2).

---

## CI

Workflow renamed: `Phase 2 v3.2 Verification` → `Phase 2 v3.3 Verification`. Final verdict: **`✅ Phase 2 v3.3 PASSES — ready to approve Phase 3`**.

All v3.1 asset checks kept (Filament published, Vite manifest exists with required entries). v3.3 tests run inside the existing `php artisan test` step — no separate steps needed.

---

## Manual verification checklist

Walk this top-to-bottom. Each item should pass. **Open browser DevTools → Network throughout** to confirm no 419s.

| # | Action | Expected |
|---|---|---|
| 1 | Visit `/admin/login` directly | **Properly styled Filament login page.** Filament logo top-left, indigo primary color, form is centered. No raw HTML. Network tab: all `/css/filament/*` and `/js/filament/*` requests return 200 |
| 2 | Log in at `/admin/login` as `admin@marketplace.test` / `password` | Lands on `/admin`. Full Filament sidebar visible. Marketplace / Access Control / Configuration nav groups. **No 419 banner.** |
| 3 | Click around the admin (Vendors → Vendor Packages → Settings → Users → back to Dashboard) | Stays logged in throughout. No spurious logout. No 419 |
| 4 | Log out of admin, try `/login` with the same admin credentials | Form shows error: **"Admin users must sign in via /admin/login."** Not authenticated |
| 5 | Visit `/` while logged out | "Become a vendor" + Sign in + Register all visible. Language switcher visible (English / العربية / اردو) |
| 6 | Click "Become a vendor" as a guest | URL changes to `/login?redirect=/vendor/apply`. Login form rendered. Admin hint visible at bottom |
| 7 | Register a fresh account at `/register` | After submit, lands on `/`. **No 419.** Logged in as customer |
| 8 | Click "Become a vendor" while logged in as a customer | URL changes to `/vendor/apply`. Form renders. **Does NOT redirect to /login** |
| 9 | Submit the vendor application | Lands on `/vendor` with pending banner. **No 419** |
| 10 | Click العربية in the header | Page reloads in Arabic. RTL layout. "Become a vendor" reads "كن بائعًا". All translated labels switch |
| 11 | Click اردو | Page reloads in Urdu. RTL layout. "Become a vendor" reads "وینڈر بنیں" |
| 12 | Click English to switch back | LTR layout restored, English labels |
| 13 | Log out via the storefront header | Lands on `/`. **No 419** |
| 14 | Try `/admin/login` as a customer | Filament shows "These credentials do not match our records" (Filament refuses non-admin via `canAccessPanel()`) |

If any of these fail, send me the **exact step + the failing screenshot or DevTools Network tab**.

---

## How to apply v3.3

1. Extract `marketplace-phase-2-v3.3.tar.gz` over your existing checkout.
2. Commit + push.
3. Trigger **Generate Lock Files** workflow (only because `composer.json`/`package.json` aren't changing but the underlying scripts changed enough that a fresh hash is cleaner).
4. **Rebuild the Docker image — required this time:**
   ```bash
   docker compose down
   docker compose build --no-cache app
   docker compose up -d
   ```
   The `--no-cache` matters — the entrypoint change is in the image.
5. Confirm in the container logs that you see:
   ```
   ▶ Publishing Filament assets (always, idempotent)…
   ```
   That line is now printed every start.
6. Walk the manual checklist above.

---

## Known limitations (intentional — Phase 2 only)

| Item | Phase |
|---|---|
| Customer-specific `/account` dashboard | Phase 3+ (customers land on `/` for now) |
| Re-apply flow for rejected vendors (admin can `reopen` from Filament; no self-service yet) | Phase 9 |
| Payment processing for paid vendor packages | Phase 4 |
| Filament admin UI translated to Arabic/Urdu (the marketplace storefront is multilingual; the admin panel chrome stays English) | Future polish item — not on the roadmap |

---

## Stop discipline

**Phase 3 (product catalog) is still untouched.** After CI is green AND the 14-step manual checklist passes, reply **"approve Phase 3"** and I'll proceed. If anything fails, the failing-step screenshot is the fastest path to a v3.4. I will not start Phase 3 until you confirm.
