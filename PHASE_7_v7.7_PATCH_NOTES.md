# Phase 7 v7.7 — unused-import fix + sandbox tsc workflow

**Status:** Targeted fix on top of Phase 7 v7.6 + permanent CI sub-check to make this bug class fail loud with a Phase 7-tagged message. Pending CI verification.
**Scope:** 1-line edit to `resources/js/Pages/Orders/Show.tsx` + 1 new CI sub-check + a process change I owe the developer (I ran real tsc in my sandbox this time).

---

## Symptom (the developer's report)

`npm run build` failed with:

```
resources/js/Pages/Orders/Show.tsx:1:16 - error TS6133:
'router' is declared but its value is never read.
```

The TypeScript compiler's `noUnusedLocals: true` setting in `tsconfig.json` rejected the imported `router` symbol because it's listed in the import but never referenced.

---

## Root cause

`resources/js/Pages/Orders/Show.tsx` line 1:

```ts
// v7.6 — buggy
import { Link, router, useForm } from '@inertiajs/react';
```

`router` was imported but the file uses only `Link` and `useForm`. This was a left-over from an earlier edit that never made it to the runtime code.

---

## Why I shipped this broken (the honest answer)

My pre-packaging workflow ran:

1. PHP brace balance (raw count)
2. Python validators (schema-vs-code, unique-index, null-vs-NOT-NULL pre-flights)
3. CI YAML parse
4. Permission catalogue dedupe
5. v7.6 lazy-load static checks

**It did NOT run `tsc --noEmit` against the actual TypeScript code.** Earlier turns DID set up `tsc` with stubs (in `/home/claude/marketplace/node_modules/@inertiajs/{react,core}` + `node_modules/react` + `node_modules/lucide-react`) but those were used only for TS2344 generic-constraint checks in Phase 6 v7.3. After that work, the stubs + verify config were always deleted before packaging — and never rebuilt for unrelated changes like the v7.6 lazy-load fixes.

The CI frontend job (`npm run typecheck` + `npm run build`, lines 2409 + 2415 of `.github/workflows/ci.yml`) **would have caught this**. But the developer ran `npm run build` locally before CI completed for v7.6, so they saw it first.

**v7.7 changes both sides:**
1. Fixes the immediate import (1 character of code change)
2. **Adds a Phase 7 v7.7 CI sub-check** to the frontend job that explicitly fails with a Phase 7-tagged actionable message if any TS6133/TS6196 error appears — so future regressions are immediately traceable to "Phase 7 v7.7 unused-identifier bug class" instead of getting lost in generic tsc output.
3. **Establishes a new pre-packaging step in my sandbox workflow**: install tsc stubs (as in Phase 6 v7.3 work), create `tsconfig.verify.json` with `strict: false` + `noUnusedLocals: true` + `noUnusedParameters: true` (to isolate unused-identifier errors from stub-noise), run `tsc --noEmit -p tsconfig.verify.json`, assert zero TS6133/TS6196 errors, delete stubs + verify config before packaging.

---

## What v7.7 changes

### 1. The actual fix (1 line)

```diff
-import { Link, router, useForm } from '@inertiajs/react';
+import { Link, useForm } from '@inertiajs/react';
```

Verified no other usage of `router` anywhere in the file:

```bash
$ grep -n "router" resources/js/Pages/Orders/Show.tsx
(no output — confirmed unused)
```

### 2. Project-wide unused-identifier audit (the user's specific ask)

I set up tsc with stubs for `@inertiajs/{react,core}`, `react` (with JSX namespace + jsx-runtime), and `lucide-react`, then created a verify-only `tsconfig.verify.json`:

```jsonc
{
    "extends": "./tsconfig.json",
    "compilerOptions": {
        // Disable strict + implicit-any rules so stub noise doesn't drown the signal
        "strict": false, "noImplicitAny": false, "noImplicitReturns": false,
        "noFallthroughCasesInSwitch": false,
        // The ONLY checks we care about:
        "noUnusedLocals": true, "noUnusedParameters": true,
        "skipLibCheck": true, "allowJs": false
    }
}
```

Ran `tsc --noEmit -p tsconfig.verify.json` against the whole codebase:

```
TS6133 (unused locals/imports/parameters): 1   ← only the developer's report
TS6196 (unused types):                     0
```

After the fix:

```
TS6133: 0
TS6196: 0
```

**There are zero unused identifiers anywhere in the project**, in `resources/js/Pages/Orders/**`, `resources/js/Pages/Vendor/**`, `resources/js/Pages/Products/**`, `resources/js/Components/**`, or any other React/TypeScript file. The single TS6133 was the one the developer reported.

The stubs + verify config are deleted before packaging — `node_modules/` and `tsconfig.verify.json` are NOT in the shipped archive.

### 3. New CI sub-check — `Phase 7 v7.7 — no unused TypeScript imports / locals / parameters`

Added to the frontend job AFTER `npm run build`:

```yaml
- name: Phase 7 v7.7 — no unused TypeScript imports / locals / parameters (prevents v7.6 TS6133 bug class)
  run: |
    set +e
    npx tsc --noEmit 2>&1 > /tmp/v77-tsc.log
    TSC_EXIT=$?
    set -e

    TS6133_COUNT=$(grep -cE "error TS6133" /tmp/v77-tsc.log || echo 0)
    TS6196_COUNT=$(grep -cE "error TS6196" /tmp/v77-tsc.log || echo 0)

    if [ "$TS6133_COUNT" -gt "0" ] || [ "$TS6196_COUNT" -gt "0" ]; then
        echo "::error::Phase 7 v7.7 unused-identifier check FAILED — TS6133=$TS6133_COUNT, TS6196=$TS6196_COUNT"
        echo "::error::This is the same bug class the developer reported for v7.6 (Orders/Show.tsx had an unused 'router' import)."
        # ...emits one annotation per file:line for each unused identifier...
        exit 1
    fi

    if [ "$TSC_EXIT" -ne "0" ]; then
        # tsc failed for some OTHER reason (not unused-identifier) — also a build break
        head -20 /tmp/v77-tsc.log
        exit 1
    fi

    echo "v7.7 OK — npm run build clean + tsc finds 0 unused imports/locals/parameters/types ✓"
```

The existing `npm run typecheck` step at line 2409 already catches TS6133. This new step **provides a Phase 7-tagged error message** that points future regressions at the v7.7 bug class instead of getting lost in generic tsc output. It runs AFTER `npm run build` so the build step still fails the job first if there's a different break.

### 4. Verdict bumped

`✅ Phase 7 v7.7 PASSES — ready to approve Phase 8`

---

## Files touched in v7.7

| File | Change |
|---|---|
| `resources/js/Pages/Orders/Show.tsx` | 1-line edit: remove `router` from `@inertiajs/react` import |
| `.github/workflows/ci.yml` | +45 lines in frontend job (new Phase 7 v7.7 sub-check) + verdict bumped in laravel job's result block |
| `PHASE_7_v7.7_PATCH_NOTES.md` | This file (new) |
| `PHASE_7_REPORT.md` | v7.7 update section appended |
| `README.md` | v7.7 changelog prepended; status header bumped |

**Files NOT touched in v7.7:** every other file in the project. This is a one-line code fix + one CI guard. No business logic, schema, controllers, models, services, seeders, env, permissions, or other React files.

---

## Verification I ran in the sandbox (this time, properly)

I cannot run `php artisan migrate:fresh --seed`, `php artisan test`, or `npm ci && npm run build` in the sandbox (network 403, no npm install). What I verified:

- **Sandbox-state regression check**: working tree restored fresh from the shipped v7.6 archive at the start of this turn — confirmed `DemoSeeder.php` at 1586 lines (v7.6 baseline).
- **Real tsc run** (the new pre-packaging step):
  - Set up stubs for `@inertiajs/{react,core}` (Page<SharedProps>, Link, router, Head, useForm), `react` (JSX namespace + jsx-runtime + hooks + event types), `lucide-react` (icon components used in Phase 7 files).
  - Created `tsconfig.verify.json` extending project `tsconfig.json` with strict checks off but `noUnusedLocals: true` + `noUnusedParameters: true`.
  - Before fix: `TS6133=1, TS6196=0` — the exact developer report.
  - After fix: `TS6133=0, TS6196=0` — zero unused identifiers anywhere in the project.
- **Stub cleanup**: `node_modules/` + `tsconfig.verify.json` deleted before tar/zip; leak check on the shipped archive confirms `0 node_modules entries, 0 tsconfig.verify.json entries`.
- **CI YAML parses**: valid.
- **Final Phase 7 CI step count**: 14 (12 in laravel job, 2 in frontend job — main customization end-to-end + new v7.7 unused-identifier check).
- **All previous defenses preserved**: v7.4 model safeguard in `CustomizationProof.php`, v7.5 `MAIL_MAILER=log` default + User override + RegisterController wrap, v7.6 four eager-load sites — verified by direct content inspection of the shipped archive.

---

## Developer testing checklist after pulling v7.7

```bash
git pull
composer install
cp .env.example .env       # or keep your existing .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan migrate:fresh --seed   # twice — confirms idempotency
npm ci
npm run typecheck                  # must pass — this is the v7.7 fix verification
npm run build                      # must pass
php artisan test                   # all Phase 7 scenarios should pass (42 total)
```

**The bug repro** — on a v7.6 checkout, `npm run build` would error on `Orders/Show.tsx:1`. After pulling v7.7, the same command runs clean. If you still see TS6133 errors after v7.7, run `git status` to confirm you actually pulled the new `Show.tsx`.

---

## Accountability — eighth Phase 7 release

This is the eighth Phase 7 release. The pattern of bugs has shifted:

| Versions | Bug type | Why I missed it pre-shipping |
|---|---|---|
| v7.0–v7.4 | Seeder / model / file-system issues | No PHP runtime in sandbox; static analysis had gaps that each subsequent CI guard closed |
| v7.5 | Environment config | I didn't notice the Docker-only default in `.env.example` |
| v7.6 | Lazy-load regression | I never ran the strict-mode Pest tests in my sandbox; the negative test added in v7.6 now makes this impossible to ship silently |
| **v7.7** | **TypeScript unused import** | **I never ran `tsc` in my sandbox before declaring the package ready** |

The v7.7 fix isn't just the 1-line code change — it's a permanent workflow change for me. Every future Phase 7+ release must include a real `tsc --noEmit -p tsconfig.verify.json` run with the unused-identifier checks isolated from stub noise. The CI sub-check is the backstop; the sandbox check is the first line of defense.

**Phase 7 v7.7 STOPS HERE. Do not start Phase 8 until CI shows `✅ Phase 7 v7.7 PASSES` AND your developer confirms `npm run build` exits 0 locally.**
