# Phase 11B.3 v11B.3.3 — Rollback Procedure

## Tier 1 — Revert only the CSS fix

Restores the pre-v11B.3.3 CSS (with the aggressive `overflow-wrap: anywhere` — letter-by-letter wrap comes back).

```bash
tar -xzf marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz \
    --strip-components=1 --overwrite marketplace/resources/css/app.css

npm ci && npm run build
```

**Use this when**: v11B.3.3 CSS changes accidentally break a legitimate long-URL layout somewhere. But note that the correct fix is to add `class="break-anywhere"` to that element, not to roll back the whole CSS improvement.

## Tier 2 — Full code revert to v11B.3.2 baseline

Reverts every v11B.3.3 code change. No schema changes to roll back.

```bash
tar -xzf marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

cat VERSION                              # → Phase 11B.3 v11B.3.2
php artisan test --filter=Phase11B32     # 37 scenarios still pass
```

**Use this when**: v11B.3.3 introduces a regression that Tier 1 doesn't cover.

## Tier 3 — Full revert past v11B.3.2 as well

Chain rollback: apply Tier 2 to reach v11B.3.2 baseline, then follow `PHASE_11B_3_2_ROLLBACK.md` to go further back to v11B.3.1 baseline. Developer's responsibility.

## What NEVER to do

- Do NOT modify the immutable `marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz` archive
- Do NOT delete rows from the `settings` table — the pre-v11B.3.3 code doesn't read from it but data preservation is safer
- Do NOT run `php artisan migrate:fresh` in production

## Recovery notes

- If the storefront shows "Undefined property: siteSettings" after Tier 2 rollback: rebuild frontend (`npm run build`) — old bundle references v11B.3.3 destructuring
- If CSS var injection block breaks after edits: it's wrapped in try/catch so `$appearance = []` on any failure; page still renders

## Emergency: only the letter-by-letter wrap fix should be preserved

If a developer wants to keep the CSS fix but roll back everything else:

```bash
# Save the v11B.3.3 CSS
cp resources/css/app.css /tmp/v11b33-app.css

# Tier 2 revert
tar -xzf marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz \
    --strip-components=1 --overwrite

# Re-apply just the CSS
cp /tmp/v11b33-app.css resources/css/app.css

npm ci && npm run build
```
