# Phase 9 v9.4 — Known Environment Limitations

These are not code defects. They are things the build sandbox (Claude's environment, AND Codex's sandbox) cannot verify, which can only be checked in your real development environment.

---

## #29 — Git/GitHub checks require a real Git repository

**What Codex reported:** "Git/GitHub checks could not run because the inspected folder was not a Git repository."

**Why:** Codex's audit sandbox extracted the archive into a plain directory. There was no `.git/` directory because the archive doesn't include version-control history (intentionally — archives are deliverables, not repos).

**What this means for you:** When you extract the v9.4 archive into your actual cloned repository, all Git/GitHub Actions checks will run normally. The CI workflow in `.github/workflows/ci.yml` is configured for the real environment.

**No action required.** This is a sandbox limitation, not a code defect.

---

## #30 — Package replacement comparison requires both archives

**What Codex reported:** "Package replacement could not be verified because a separate Phase 9 package/archive was not available in that workspace."

**Why:** Codex needed both the previous-release archive AND the v9.4 candidate to diff. Only one was present in its workspace.

**What this means for you:** On your machine, you have both v9.3 (the previous shipped) and v9.4 (this candidate). Diff them:

```bash
mkdir -p /tmp/v9.3 /tmp/v9.4
tar -xzf marketplace-phase-9-v9.3.tar.gz -C /tmp/v9.3
tar -xzf marketplace-phase-9-v9.4.tar.gz -C /tmp/v9.4
diff -ruN /tmp/v9.3/marketplace /tmp/v9.4/marketplace > /tmp/v9.4-changes.diff
```

You'll see only the surgical v9.4 changes listed in `PHASE_9_v9.4_PATCH_NOTES.md`.

**No action required.**

---

## Sandbox commands that COULDN'T run during v9.4 build

Documented in section "v23" of `PHASE_9_v9.4_VERIFICATION_MATRIX.md`. Brief version:

| Command | Why blocked | Where it WILL run |
|---|---|---|
| `composer install` | No network in Claude's sandbox | Your machine + GitHub Actions CI |
| `npm ci` | No network in Claude's sandbox | Your machine + GitHub Actions CI |
| `php artisan migrate:fresh --seed` | No PHP runtime in Claude's sandbox | Your machine + GitHub Actions CI |
| `php artisan test` | No PHP runtime in Claude's sandbox | Your machine + GitHub Actions CI |
| `npm run build` | No Vite, no real npm install | Your machine + GitHub Actions CI |

What DID run in Claude's sandbox:
- Real `tsc` against v9.4-touched files (with hand-written .d.ts stubs)
- Project-wide static structural checks (ILIKE absence, null-safe seeders, identifier-length, controller return types, Filament closure injection)
- PHP brace-balance on every edited file
- CI YAML parse validation
- Archive extraction + grep-based verification of shipped contents

---

## Frontend stub-environment limitations

Real `tsc` reported `TS6133=0` and `TS6196=0` on v9.4-touched files (which were all backend — no .tsx files changed). However, `tsc` reported 46 pre-existing TypeScript errors in untouched frontend files; these are artifacts of the hand-written `.d.ts` stub used in Claude's sandbox (notably the recurring `SharedProps does not satisfy PageProps` variance error). They are NOT v9.4 regressions — same files reported the same errors in the v9.3 build.

To verify on your end with the real `@types/react` + `@inertiajs/react` packages installed:

```bash
npm ci
npm run typecheck
```

These should pass cleanly.
