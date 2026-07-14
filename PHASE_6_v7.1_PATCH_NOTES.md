# Phase 6 v7.1 — APP_KEY pre-seed guard (targeted fix)

**Status:** Targeted fix on top of Phase 6 v7.0. Pending CI verification (only true gate).
**Scope:** Setup ergonomics only. **No business-logic changes.** Encryption of supplier credentials is preserved as-is.

---

## Symptom (what the developer saw)

On a fresh local clone:

```
php artisan migrate:fresh --seed
```

```
Illuminate\Encryption\MissingAppKeyException

No application encryption key has been specified.
```

The crash happened partway through the seed — at the moment Phase 6's `seedSupplierIntegrationsAndProducts()` tried to persist a `SupplierIntegration` row with the `credentials` field, which is cast as `encrypted:array`. The Encrypter requires `APP_KEY`; if it's blank, Laravel throws this exception.

A second, unrelated screenshot showed:

```
php artisan migrate:fresh --seed.    # ← trailing dot is a typo
The "--seed." option does not exist.
```

That second one is a typing mistake, not a code bug — v7.1 ensures all our documentation and CI use the correct command without the trailing dot.

---

## Root cause

Two missing safeguards:

1. **No pre-seed APP_KEY guard.** `DemoSeeder::run()` proceeded into encrypted writes even when `config('app.key')` was blank. The developer got Laravel's cryptic stack trace instead of a clear next-step message.
2. **`GETTING_STARTED.md` did not mention `php artisan key:generate` at all.** The correct sequence on a fresh clone — `cp .env.example .env && php artisan key:generate && php artisan migrate:fresh --seed` — was nowhere in the setup docs.

`.env.example` was already correct (`APP_KEY=` with no pre-filled value). CI was already correct (line 96-97 does the full setup before any migrate). The bug only manifested for a developer following local-setup docs.

---

## What v7.1 changes

### 1. Pre-seed APP_KEY guard in `DemoSeeder`

`database/seeders/DemoSeeder.php` — `run()` now checks `blank(config('app.key'))` immediately after the `testing`/`local`/`development` env gates and throws a `RuntimeException` with the exact remedy:

```
APP_KEY is missing. Phase 6 cannot seed encrypted supplier credentials without it.

Fix:
  cp .env.example .env       # if you have not done this yet
  php artisan key:generate
  php artisan migrate:fresh --seed
```

The guard is below the `app()->environment('testing')` skip, so Pest tests (which set their own APP_KEY via bootstrap) are unaffected. Encryption is **not** removed or weakened — the guard just trips earlier with a useful message.

### 2. Documentation

- `GETTING_STARTED.md` — added a "Setup (fresh clone)" callout box at the top with the three required commands in order.
- `README.md` — added a "Quick start" section right under the header banner with the same three commands.
- `PHASE_6_REPORT.md` — appended a "v7.1 update" section with this fix.
- `TROUBLESHOOTING.md` — added a top-of-file entry for `MissingAppKeyException` with the exact fix and a callout about the trailing-dot typo.

All docs now consistently use:

```
php artisan migrate:fresh --seed
```

— no trailing dot.

### 3. CI verification — new step

`.github/workflows/ci.yml` — added the step `Phase 6 v7.1 — APP_KEY setup verification (regression check on local-install crash)` after the existing Phase 6 step. Five sub-checks:

1. `.env.example` has `APP_KEY=` with no pre-filled value.
2. `.env` exists and `APP_KEY` is a generated `base64:*` key by the time the Phase 6 step runs.
3. Blanking `APP_KEY` at runtime and running `DemoSeeder::run()` produces the helpful `RuntimeException` containing `"APP_KEY is missing"` and `"php artisan key:generate"`.
4. After the real seed, the `supplier_integrations.credentials` column is NOT plaintext AND the model cast decrypts the row back to an array correctly.
5. CI itself uses the correct command — fails if anyone accidentally introduces a `migrate:fresh --seed.` (trailing dot) anywhere in the workflow file.

The final CI verdict is now:

```
✅ Phase 6 v7.1 PASSES — ready to approve Phase 7
```

### 4. Tests

`tests/Feature/Phase6DropshippingTest.php` — appended 2 new scenarios:

- `Phase 6 v7.1: SupplierIntegration encrypted credentials round-trip correctly when APP_KEY is set` — happy-path assertion that the cast returns the original array and the DB column does not contain the plaintext.
- `Phase 6 v7.1: DemoSeeder throws a helpful RuntimeException when APP_KEY is blank in local env` — temporarily nulls `config('app.key')`, switches to `local` env, calls `DemoSeeder::run()`, asserts the exception message contains both `"APP_KEY is missing"` and `"php artisan key:generate"`.

Total Phase 6 test scenarios: 25.

---

## What this does NOT change

- No business logic, no schema changes, no Filament resource changes, no React page changes, no controller changes.
- Encryption of `SupplierIntegration::credentials` is preserved — cast remains `encrypted:array`, `$hidden = ['credentials']` remains.
- All v7.0 tests, the v7.0 CI step, and the demo data are unchanged.

---

## Correct local setup (fresh clone)

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

If you skip step 2, the seeder will halt with a clear message telling you what to do — no more cryptic `MissingAppKeyException` stack trace.

Note the exact command: `php artisan migrate:fresh --seed` — **no trailing dot**.

---

## Files touched

| File | Change |
|---|---|
| `database/seeders/DemoSeeder.php` | +24 lines — pre-seed APP_KEY guard |
| `tests/Feature/Phase6DropshippingTest.php` | +48 lines — 2 new test scenarios |
| `.github/workflows/ci.yml` | +95 lines — new v7.1 verification step + verdict bump |
| `GETTING_STARTED.md` | Added "Setup (fresh clone)" callout at top |
| `README.md` | Added "Quick start" section + v7.1 changelog block |
| `PHASE_6_REPORT.md` | Appended "v7.1 update" section |
| `TROUBLESHOOTING.md` | Added MissingAppKeyException entry + trailing-dot note |
| `PHASE_6_v7.1_PATCH_NOTES.md` | This file (new) |

**Files NOT touched:** all 7 Phase 6 migrations, all 6 new models, all 3 services, all 4 Filament resources + Pages, all 3 vendor controllers, all 8 React pages, routes, VendorLayout, RolesAndPermissionsSeeder, `.env.example` (already correct).

---

## Honest stance

- Sandbox cannot run PHP / Composer / migrations. The new guard is a few lines of `blank()` + `throw new RuntimeException`; static review is sufficient to confirm it doesn't break anything else.
- The two new tests assert real runtime behaviour (one creates an integration end-to-end; the other temporarily nulls `config('app.key')`).
- The CI step explicitly blanks `APP_KEY` at runtime in a separate `tinker --execute` and asserts the helpful message fires.
- **CI is the only true verification gate.** Approve Phase 7 only after CI shows `✅ Phase 6 v7.1 PASSES`.
