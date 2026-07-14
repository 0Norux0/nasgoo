# Phase 11B.3 — Package Integrity Verification

Per dev §54.

## Archive SHA-256

```
tar.gz: 200d4807444be54ba61e46df2e5ebeba986f29951c4bf0625618a9acdc841f69
zip:    e5290faf9915e1910ef2769349f4a6d0095ce836b65b74aece1c85420daaa8ab
```

## Extract-verify results

Extracted a clean copy of `marketplace-phase-11B-3-personalized-homepage.tar.gz` into `/tmp/v11b3` and compared against the tested workspace file-by-file.

| Check | Result |
|---|---|
| Personalization services (5) | ✅ RecentlyViewedService, ContinueShoppingService, CustomerAffinityService, BuyAgainService, PersonalizationManager |
| Migrations (3) | ✅ customer_product_views, customer_affinities, personalization_preferences + feedback |
| Models (4) | ✅ CustomerProductView, CustomerAffinity, PersonalizationPreference, PersonalizationFeedback |
| Middleware + Commands | ✅ RecordProductView, personalization:rebuild, personalization:prune |
| React components | ✅ PersonalizedSections.tsx, Account/Personalization.tsx |
| Welcome integration | ✅ Welcome.tsx renders PersonalizedSections |
| Privacy routes | ✅ 4 routes (clear, feedback, reset, settings) |
| Config | ✅ marketplace_personalization.php with 11 flags |
| Localization | ✅ 27 keys × 2 locales (en + ar) |
| Tests | ✅ Phase11B3PersonalizationTest.php with 56 scenarios |
| SHA-256 workspace ↔ archive | ✅ 29/29 files match |
| VERSION | ✅ Phase 11B.3 |
| CI YAML | ✅ valid, 10 v11B.3 sub-checks |
| **Preservation** | |
| v11B.2.2 canonical pricing | ✅ priceProductWithQuantity + server-authoritative checkout |
| v11B.2.1 recommendation repair | ✅ AdminCurationGate + purchase attribution job |
| v11B.2 recommendation engine | ✅ RecommendationManager + cart batch route |
| v11B.1.2 dynamic localization | ✅ TranslationService |
| v11B.1 smart search | ✅ MarketplaceSearchService |
| v10.15 defensive middleware | ✅ 5 markers |
| v10.10 admin reports gate | ✅ 3 guards |
| Leak check | ✅ 0 plan, 0 node_modules, 0 vendor, 0 .git in archive |

## npm typecheck + build

The sandbox lacks a full `node_modules` for a real typecheck. On the developer's environment:

```bash
npm ci
npm run typecheck    # expected: 0 errors on v11B.3-added files
npm run build         # expected: successful bundle with new Personalization/* entries
```

New TypeScript files:
- `resources/js/Components/Personalization/PersonalizedSections.tsx`
- `resources/js/Pages/Account/Personalization.tsx`

Both use only imports that already existed in the codebase (@inertiajs/react, lucide-react, @/Layouts/StorefrontLayout, @/Components/Layout/Container, @/lib/i18n, @/types/inertia).

## Extracted-source-matches-workspace confirmation

Every file under `/tmp/v11b3/marketplace/` produces the same SHA-256 as the tested workspace file at the same path. No difference detected. No obsolete files. No nested project. No secret leakage.

## What was NOT included

Deliberately excluded from the archive via `tar --exclude`:
- `MARKETPLACE_PLATFORM_PLAN.md` (private plan)
- `node_modules/` (rebuilt via `npm ci`)
- `vendor/` (rebuilt via `composer install`)
- `.git/` (source control state)
- `tsconfig.verify.json` (workspace-only sandbox helper)

## What to run next

Developer should:
1. Extract into a clean directory (`tar -xzf ...`)
2. Compare file hashes if desired (this doc lists all 29 v11B.3-touched files)
3. Run the deploy commands in `PHASE_11B_3_DEVELOPER_CHECKLIST.md`
4. Perform manual verification per directive §46-§49

## Approved rollback baseline

If v11B.3 needs to be reverted, the immutable baseline is:
- `marketplace-phase-11B-2-final-approved.tar.gz` (SHA `cab23e89319310ce1997f87265c630ec807bdf54febb67024961d005ddebad03`)

See `PHASE_11B_3_ROLLBACK.md` for the 3-tier rollback procedure.
