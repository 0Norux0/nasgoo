# Phase 6 v7.2 — Foolproof guided setup (`php artisan marketplace:setup-demo`)

**Status:** Targeted ergonomics fix on top of Phase 6 v7.1. Pending CI verification (only true gate).
**Scope:** Setup ergonomics only. **No business-logic changes.** Encryption of supplier credentials is preserved.

---

## Symptom (what the developer reported after v7.1)

After v7.1, the error message itself was clearer:

```
APP_KEY is missing. Phase 6 cannot seed encrypted supplier credentials without it.
```

…but the developer still had to type **four** commands by hand to recover, and one of them (`php artisan optimize:clear`) was easy to skip if a previous half-broken run left cached config behind:

```
cp .env.example .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate:fresh --seed
```

Result: same crash kept happening on different clones, because the sequence wasn't memorable and the cache-clear step was new.

---

## What v7.2 changes (5 files; no business logic touched)

### 1. New artisan command: `php artisan marketplace:setup-demo`

`app/Console/Commands/MarketplaceSetupDemo.php` — a single guided command that does the whole sequence with friendly progress output:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Marketplace · guided demo setup (Phase 6 v7.2)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✓ .env already exists
  ✓ APP_KEY already set in .env
▶ Clearing caches (optimize:clear)…
▶ Running migrate:fresh --seed…
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✅ Demo environment ready. Login accounts (password = "password"):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Admin           → admin@marketplace.test
  Staff           → staff@marketplace.test
  Vendor          → vendor@marketplace.test
  Vendor 2        → vendor2@marketplace.test
  Customer        → customer@marketplace.test
  Pending vendor  → pending-vendor@marketplace.test
  Rejected vendor → rejected-vendor@marketplace.test

  Next steps:
    composer install && npm install && npm run build
    php artisan serve   # then open http://localhost:8000
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**Steps the command performs in order:**

1. **`.env` exists** — if not, copies from `.env.example` (asks first; auto-accepts with `--force`).
2. **`APP_KEY` is set** — reads `.env` directly from disk (NOT from cached config), checks for `APP_KEY=base64:...`. If missing, runs `php artisan key:generate --force` and reloads the env in-process so the next sub-command sees the new value.
3. **`optimize:clear`** — flushes any stale cached config from a previous half-broken run.
4. **`migrate:fresh --seed --force`** — the actual work.
5. **Prints all 7 demo login accounts.**

**Refuses to silently continue past missing APP_KEY.** If the developer says no to the auto-generate prompt, the command exits non-zero and prints the exact 4-command remedy.

**Flags:**

| Flag | Purpose |
|---|---|
| `--force` | Skip every confirmation prompt — auto-accept defaults. Required for CI/scripts. |
| `--skip-migrate` | Run only the env + cache-clear steps; useful for sanity-checking the env before a manual migrate. |

### 2. Updated `DemoSeeder` error message

When seed code hits the APP_KEY guard, it now prints the full v7.2 remedy:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
APP_KEY is missing. Phase 6 cannot seed encrypted supplier
credentials without it.

Run these commands exactly, in order:

  cp .env.example .env
  php artisan key:generate
  php artisan optimize:clear
  php artisan migrate:fresh --seed

Or run the foolproof guided command (does all of the above):

  php artisan marketplace:setup-demo

Note: the command is --seed (no trailing dot).
"--seed." with a dot is rejected by Laravel.

See PHASE_6_v7.2_PATCH_NOTES.md and TROUBLESHOOTING.md.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

### 3. `bootstrap/app.php` — explicit command discovery

Added `->withCommands([__DIR__.'/../app/Console/Commands'])` so `marketplace:setup-demo` is registered unambiguously. (Laravel 11's default already scans this dir; being explicit documents intent and is robust against future framework changes.)

### 4. Documentation

- `README.md` — Quick start banner now leads with `php artisan marketplace:setup-demo`, with the 4-command manual sequence shown as the fallback.
- `GETTING_STARTED.md` — same change at the top.
- `TROUBLESHOOTING.md` — `MissingAppKeyException` entry now points at the guided command first.
- `PHASE_6_REPORT.md` — appended v7.2 update section.

All docs use the correct command (`php artisan migrate:fresh --seed` — no trailing dot).

### 5. CI verification — new step

`.github/workflows/ci.yml` — new step `Phase 6 v7.2 — marketplace:setup-demo guided command works end-to-end` after the v7.1 step. **Five sub-checks:**

1. `marketplace:setup-demo` is registered (via `php artisan list --raw | grep`).
2. `marketplace:setup-demo --force` runs the whole sequence to exit code 0 AND prints both "Demo environment ready" and `admin@marketplace.test` in its output.
3. After the guided command runs, all Phase 6 demo data is present in the DB (≥5 supplier platforms, ≥1 integration, ≥3 supplier products, ≥1 published dropship product, ≥1 supplier order) AND the credentials column is encrypted at rest (no plaintext).
4. `marketplace:setup-demo --force --skip-migrate` runs the env-check path to exit code 0 AND does NOT print the demo-accounts block (proving it actually skipped the migrate).
5. `DemoSeeder` helpful error string contains all 5 required substrings: `APP_KEY is missing`, `php artisan key:generate`, `php artisan optimize:clear`, `php artisan migrate:fresh --seed`, `php artisan marketplace:setup-demo`.

Final verdict bumped to `✅ Phase 6 v7.2 PASSES — ready to approve Phase 7`.

### 6. Tests

`tests/Feature/Phase6DropshippingTest.php` — appended 3 new hermetic scenarios (now 28 total Phase 6 scenarios):

- `marketplace:setup-demo is registered as an artisan command`
- `marketplace:setup-demo has the documented options (--force, --skip-migrate)` — asserts via the command's `InputDefinition` that both flags exist and don't accept values
- `MarketplaceSetupDemo class defines the helper methods and remedy strings` — reflection-based assertion that `ensureEnvFile`, `ensureAppKey`, `reloadDotenv`, `printMissingKeyHelp`, `printDemoAccounts`, `confirmOrAutoAccept` all exist, AND the source contains all 5 remedy strings (`cp .env.example .env`, `php artisan key:generate`, `php artisan optimize:clear`, `php artisan migrate:fresh --seed`, `php artisan marketplace:setup-demo`)

**Why no `$this->artisan('marketplace:setup-demo', ...)` test in the Pest suite:** running the command end-to-end inside a Pest test would have the side effect of copying `.env.example` over `.env` and calling `key:generate` on the developer's working tree — that mutates state that should be hermetic to the test run. CI sub-check 2 exercises the real end-to-end path on a throwaway runner instead.

The existing v7.1 helpful-error test was extended to also assert the new `optimize:clear` and `marketplace:setup-demo` strings are present.

---

## Correct local setup (fresh clone)

**Preferred (v7.2 onwards):**

```bash
php artisan marketplace:setup-demo
```

That's it. The command walks the entire setup.

**Manual sequence (if you prefer to type each step yourself):**

```bash
cp .env.example .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate:fresh --seed
```

> Note: the command is `php artisan migrate:fresh --seed` — **no trailing dot**. `--seed.` with a dot is rejected by Laravel with `The "--seed." option does not exist.`

---

## What this does NOT change

- No business logic, no schema, no Filament resources, no React pages, no controllers, no routes.
- The `encrypted:array` cast and `$hidden = ['credentials']` on `SupplierIntegration` are unchanged.
- All v7.0 + v7.1 tests, CI steps, and demo data are intact.

---

## Files touched

| File | Change |
|---|---|
| `app/Console/Commands/MarketplaceSetupDemo.php` | NEW — guided setup command (~200 lines) |
| `bootstrap/app.php` | +5 lines — explicit `->withCommands([...])` |
| `database/seeders/DemoSeeder.php` | Helpful-error message expanded to include `optimize:clear` + guided command |
| `tests/Feature/Phase6DropshippingTest.php` | +35 lines — 3 new scenarios + v7.1 assertion strengthened |
| `.github/workflows/ci.yml` | +105 lines — new v7.2 verification step + verdict bump |
| `README.md` | Quick start leads with guided command; v7.2 changelog block prepended |
| `GETTING_STARTED.md` | Same guided-command-first treatment at the top |
| `TROUBLESHOOTING.md` | MissingAppKeyException entry rewritten to point at guided command first |
| `PHASE_6_REPORT.md` | Appended v7.2 update section |
| `PHASE_6_v7.2_PATCH_NOTES.md` | This file (new) |

**Files NOT touched:** all 7 Phase 6 migrations, all 6 new models, all 3 services, all 4 Filament resources + Pages, all 3 vendor controllers, all 8 React pages, routes, VendorLayout, RolesAndPermissionsSeeder, `.env.example`.

---

## Honest stance

I cannot run PHP / Composer / migrations in the sandbox. What this build has confirmed in the sandbox:

- **PHP brace balance**: 321/321 (320 from v7.1 + 1 new command file)
- **TypeScript**: 0 errors (no React changes in v7.2)
- **YAML / JSON parse**: ci.yml, package.json, composer.json all valid
- **Command source structure**: `MarketplaceSetupDemo` extends `Illuminate\Console\Command`, has a parseable `$signature`, defines `handle(): int`, and exposes the methods asserted in tests
- **CI YAML accommodation**: the v7.2 step uses only documented `php artisan` interfaces and bash idioms

What only CI can verify:

- `php artisan marketplace:setup-demo --force` actually walks all 5 steps on the GitHub Actions runner
- Demo data IS present in the DB after the guided command (sub-check 3)
- `--skip-migrate` path correctly skips the migrate stage (sub-check 4)
- The DemoSeeder helpful-error string survives unchanged when invoked via `tinker --execute` with blanked `APP_KEY` (sub-check 5)

I'm not endorsing this as "tested" — only as **statically clean and CI-verifiable**. The CI summary remains the only authoritative gate.

**Approve Phase 7 only after CI shows `✅ Phase 6 v7.2 PASSES`.**
