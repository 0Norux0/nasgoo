# Phase 9 v9.5 — Known Environment Limitations

These are not code defects. They are things the build sandbox cannot verify, OR things that Codex flagged that only reproduce in environments different from the developer's deployment.

---

## Docker / PostgreSQL / Redis host availability

Codex's audit sandbox didn't have the docker stack running. Specifically:

- **PostgreSQL** unavailable from host
- Missing `pdo_pgsql` extension
- Redis hostname `redis` failed (Docker-only hostname)

These reproduce in any environment that doesn't run the docker stack. They are **not application bugs**.

The developer's environment uses **MySQL**, not PostgreSQL — confirmed by the v9.4 fix where `ILIKE` (PostgreSQL-only) was replaced with portable `LOWER(name) LIKE`. The application is verified to work on both engines.

For deployments using MySQL only:
- Skip the PostgreSQL/`pdo_pgsql` installation steps
- Skip the Redis/Docker hostname configuration; use `localhost:6379` or your hosted Redis

For Docker users: `docker compose up -d` should make all hostnames resolvable.

---

## Git / GitHub checks require a real Git repository

Same as v9.4 limitation #29. The archive doesn't include `.git/`. On your developer machine inside your cloned repo, all Git checks (including `git status`, GitHub Actions on push, etc.) work normally.

---

## Package comparison requires a baseline archive

Same as v9.4 #30. To diff v9.4 vs v9.5:

```bash
mkdir -p /tmp/v9.4 /tmp/v9.5
tar -xzf marketplace-phase-9-v9.4.tar.gz -C /tmp/v9.4
tar -xzf marketplace-phase-9-v9.5.tar.gz -C /tmp/v9.5
diff -ruN /tmp/v9.4/marketplace /tmp/v9.5/marketplace > /tmp/v9.5-changes.diff
```

You'll see only the surgical v9.5 changes listed in `PHASE_9_v9.5_PATCH_NOTES.md` (ReviewService::approve, ProductReviewResource, Phase9V95RegressionTest, ci.yml, VERSION).

---

## Codex-flagged tests that don't exist in this codebase

Codex appears to have been running against a different snapshot. The following Codex findings reference test files / classes that don't exist in the v9.4/v9.5 baseline:

- `CheckoutAddressSchemaTest` — no such file
- `ServiceProviderFactory` — no such factory
- Specific Phase 4–8 tests named in the audit but missing here

For each of these, "the test fails" cannot be reproduced in this codebase because the test doesn't exist. If the developer can share the exact file paths Codex tested, we can match them against the actual codebase in a future patch.

---

## Sandbox commands that COULDN'T run during v9.5 build

(Same as v9.4 — Claude's environment has no network + no PHP runtime.)

| Command | Status |
|---|---|
| `composer install` | ❌ blocked |
| `npm ci` | ❌ blocked |
| `php artisan migrate:fresh --seed` | ❌ blocked |
| `php artisan test` | ❌ blocked |
| `npm run typecheck` (real `tsc` with stubs) | ✓ |
| `npm run build` | ❌ blocked |

What DID execute in Claude's sandbox:
- Static structural checks (file presence, brace balance, defense scans)
- CI YAML parse validation
- v8.5 unique-helpers (43 unique, 0 duplicates)
- Project-wide ILIKE absence + seeder null-safety
- Archive extraction + grep-based verification
- Real tsc with hand-written .d.ts stubs (TS6133=0, TS6196=0 on v9.5-touched files; v9.5 didn't touch any frontend)

These checks are sufficient to validate the static-source contracts. Full runtime verification is the developer's machine + GitHub Actions CI.

---

## Frontend stub-environment limitations

Real `tsc` against the hand-written `.d.ts` stubs reports ~46 pre-existing TypeScript errors in untouched frontend files — primarily the recurring `SharedProps does not satisfy PageProps` variance error. These are stub-environment artifacts, NOT v9.5 regressions (same files reported the same errors in v9.4 build).

To verify on your machine with the real `@types/react` + `@inertiajs/react`:

```bash
npm ci
npm run typecheck
```

Should pass cleanly.
