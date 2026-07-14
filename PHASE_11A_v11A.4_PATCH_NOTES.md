# Phase 11A v11A.4 — Patch Notes

## Summary

Four targeted fixes for the issues identified in dev's v11A.3 verification pass.

## Changes

### §2 Catalog gutter
- `resources/js/Pages/Catalog/Index.tsx` — wrap page content in canonical `<Container>` (was edge-to-edge). Sidebar now has 16/24/32/40px responsive gutter matching storefront chrome. Grid bumped from `[240px_1fr]` to `[260px_minmax(0,1fr)]` to prevent overflow expansion.

### §3 Contrast tightening
- Catalog category count badges: slate-500 → slate-600 (xs-text comfort)
- Catalog title parenthetical (24): slate-500 → slate-600
- Catalog empty state: slate-500 → slate-700 (AAA on white)
- Catalog strike-through: slate-500 → slate-600 (semantic de-emphasis preserved via strikethrough)
- Search icon and placeholder: slate-400 → slate-500 (cosmetic, both were already acceptable per WCAG icon/placeholder exemptions)

### §4 Live search suggestions
- **NEW** `app/Http/Controllers/SearchSuggestionController.php`
- **NEW** `routes/web.php` entry: `GET /search/suggestions` with `throttle:120,1`
- **NEW** `resources/js/Components/common/SearchBar.tsx` — debounced (300ms) typeahead component with keyboard nav, listbox semantics, abort-stale protection, JS-disabled form fallback
- `resources/js/Layouts/StorefrontLayout.tsx` — desktop search form replaced with `<SearchBar variant="desktop">`

Endpoint behavior:
- Min 2-char query
- Cap 5 results per group (products, categories, services)
- Excludes drafts/unpublished/archived
- Service detail must be active
- Prefix LIKE (index-friendly) with escaped wildcards
- Returns only id/slug/name/price/currency/href — no descriptions

### §5/§6 Arabic localization
- `resources/js/Components/common/LangSwitcher.tsx` — `DISPLAY_LOCALES = ['en', 'ar']` filter (Urdu had been showing as a second-Arabic option due to Arabic script "اردو"). Urdu translation files preserved on disk for future activation.
- `lang/en.json` + `lang/ar.json` — both expanded from 151 → 243 keys (+92 v11A surface keys covering header, hero, sections, footer, search, catalog sidebar)
- `resources/js/Pages/Welcome.tsx` — useT() wired with 45 t() calls
- `resources/js/Layouts/StorefrontLayout.tsx` — useT() wired with 58 t() calls
- Active button styling bumped to `bg-brand-700 font-semibold` for visible selection
- `data-testid="lang-switcher-v11a4"` marker added

## Tests

- **NEW** `tests/Feature/Phase11AV1Hot4RegressionTest.php` — 34 Pest scenarios covering all 4 issue areas + preservation

## CI

- `.github/workflows/ci.yml` — +6 v11A.4 sub-checks (catalog wrap, LangSwitcher dedup, translation parity, useT wiring, search endpoint+frontend, v11A.4 Pest filter)

## VERSION

`Phase 11A v11A.3` → `Phase 11A v11A.4`

## Backend impact

- **1 new PHP file** (SearchSuggestionController)
- **0 modifications** to existing PHP files
- **1 new route** (/search/suggestions)
- All v10.0-v10.16 backend SHA-identical to Phase 10 final-approved

## Frontend impact

- **4 modified TSX files** (Catalog/Index, Welcome, StorefrontLayout, LangSwitcher)
- **2 new TSX files** (SearchBar component, no other new components)
- v11A primitives untouched (Button, primitives, ProductCard, Container)

## Counts

| Metric | v11A.3 | v11A.4 | Delta |
|---|---|---|---|
| CI sub-checks | 86 | 92 | +6 |
| Pest scenarios | 340 | 374 | +34 |
| Unique Pest helpers | 105 | 109 | +4 |
| Translation keys (en/ar) | 151 each | 243 each | +92 |
| Welcome.tsx t() calls | 0 | 45 | +45 |
| StorefrontLayout t() calls | ~5 | 58 | +53 |
| New PHP files | — | 1 | +1 |
| New routes | — | 1 | +1 |
| New TSX components | — | 1 | +1 |

## Deploy commands

```bash
php artisan optimize:clear
rm -rf public/build/
rm -rf node_modules/.vite/
npm ci
npm run typecheck
npm run build
php artisan test
```

## Phase 11A v11A.4 STOPS HERE

No Phase 11B recommendation engine begun. Pending dev verification.
