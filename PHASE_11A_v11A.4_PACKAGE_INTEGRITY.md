# Phase 11A v11A.4 — Package Integrity Report

Per dev §13.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 11A v11A.4`
- 1 PHP file ADDED (SearchSuggestionController); 0 PHP files modified vs Phase 11A v11A.3
- 1 route ADDED (`GET /search/suggestions`)
- 2 frontend files ADDED (SearchBar.tsx, *no other new components)
- 4 frontend files MODIFIED (Catalog/Index, Welcome, StorefrontLayout, LangSwitcher)
- 2 translation files MODIFIED (en.json + ar.json: 151 → 243 keys)
- 1 new test file (Phase11AV1Hot4RegressionTest)

## Files INSIDE the archive (v11A.4-touched)

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `2f83f2b6ab9299e6d1e1009421a1685ad15d55b542aa294fec243cfe11388594` |
| 2 | `resources/js/Pages/Catalog/Index.tsx` | `804047a579df04186608a27337271b4279087f7af0fd92f3e6379d07fcf68671` |
| 3 | `resources/js/Pages/Welcome.tsx` | `52f6e5f81f50beb24c91fcc3bdd6dfa8a11a6cab86e433ac2db58c63a50c1629` |
| 4 | `resources/js/Layouts/StorefrontLayout.tsx` | `0570c36e8f33869befa4050f188bdcab3396094fd7edb292356a319fd1a7100f` |
| 5 | `resources/js/Components/common/LangSwitcher.tsx` | `62ffc8cc52fdb6e5e766c63b2bd5dde8e688b7f307ef25ef5bd99f3e7b31ee20` |
| 6 | `resources/js/Components/common/SearchBar.tsx` | `145f98c8d53d38c552ce719241768ce27222f8cd29a885488045d2bd6ee02441` |
| 7 | `app/Http/Controllers/SearchSuggestionController.php` | `54dbc1f6d95edbabd41de1753e6e90dc64276b54bc290672e144072a290c5aae` |
| 8 | `routes/web.php` | `a9ed7d270d4ba4e3f9a160feb3170ed4f57fe48fd3d7ece4759a2707cd81b0a7` |
| 9 | `lang/en.json` | `4f41bb607dc401f3f5023b73e8b257553d262fcd7987193c8f2c104f755a022c` |
| 10 | `lang/ar.json` | `2728d4c2f8a7b59265198509b627fd7500ef06f1883f2d2f8d4cb614e2ad2bdd` |
| 11 | `tests/Feature/Phase11AV1Hot4RegressionTest.php` | `106f97cb930f1afbd8b87be1a0533e7b860d5c025f929f9da6d823070b14ad5e` |
| 12 | `.github/workflows/ci.yml` | `6ff59513d9a253a2215fbedffd4cc3048e28f3d473f816c8cc91804fdf1d939c` |

## Files SHA-IDENTICAL to v11A.3 (no regression / accidental change)

- `resources/js/Components/Layout/Container.tsx` (v11A.2 canonical Container)
- `resources/js/Components/ui/v11/ProductCard.tsx` (v11A.3 padding + contrast)
- `resources/js/Components/ui/v11/Button.tsx`
- `resources/js/Components/ui/v11/primitives.tsx`
- `resources/css/app.css`
- `tailwind.config.js` (v11A.2 safelist preserved)
- `tests/Feature/Phase11ARegressionTest.php`
- `tests/Feature/Phase11AV1Hot1RegressionTest.php`
- `tests/Feature/Phase11AV1Hot2RegressionTest.php`
- `tests/Feature/Phase11AV1Hot3RegressionTest.php`
- `resources/js/Pages/Services/Index.tsx` (v11A.3 palette migration)

## All v10.0-v10.16 PHP files SHA-identical to Phase 10 final-approved

Verified by source diff. The one new PHP file (SearchSuggestionController) does not modify any existing controller/middleware/service.

## §13 Extract-verify procedure

After build:
1. Extract into `/tmp/v11ah4/` — clean
2. `VERSION` = `Phase 11A v11A.4`
3. Catalog/Index.tsx has exactly 1 `<Container>` wrap with `lg:grid-cols-[260px_minmax(0,1fr)]`
4. LangSwitcher has `DISPLAY_LOCALES = ['en', 'ar']` filter
5. en.json + ar.json both have 243 keys with identical key sets
6. SearchSuggestionController.php exists with `MIN_QUERY_LENGTH = 2`, `RESULTS_PER_GROUP = 5`, `STATUS_PUBLISHED` filter
7. `/search/suggestions` route registered with `throttle:120,1`
8. SearchBar.tsx exists with `DEBOUNCE_MS = 300`, AbortController, listbox semantics, keyboard nav
9. Welcome.tsx imports useT + has 45 t() calls
10. StorefrontLayout.tsx imports useT + has 58 t() calls + uses `<SearchBar>`
11. Tests file has 34 Pest scenarios
12. 12/12 v11A.4-touched files SHA-identical workspace ↔ archive
13. v11A.2 canonical Container still present + obsolete path still absent
14. v11A.2 Tailwind safelist still includes all 7 critical container classes
15. v11A.3 ProductCard.tsx body uses `p-4 sm:p-5` and slate-500 strikethrough
16. All 7 v11A homepage section testids preserved
17. v10.6 mobile-categories-toggle preserved
18. v10.16 null-safe permissions pattern preserved
19. All v10.0-v10.16 backend markers preserved
20. CI YAML valid with 6 new v11A.4 sub-checks
21. No node_modules / vendor / .git / tsconfig.verify in archive
22. No MARKETPLACE_PLATFORM_PLAN.md leak
