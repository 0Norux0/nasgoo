# Phase 11A v11A.4 — Developer Verification Checklist

Per dev §8 + §13.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-11A-v11A.4-catalog-search-arabic.tar.gz.sha256
tar -xzf marketplace-phase-11A-v11A.4-catalog-search-arabic.tar.gz --strip-components=1 --overwrite
```

## §10 Required commands

```bash
php artisan optimize:clear
rm -rf public/build/                # force fresh CSS build
rm -rf node_modules/.vite/          # clear Vite cache

php artisan route:list | grep -i search    # → search.suggestions  GET  /search/suggestions
php artisan route:list | grep -i locale    # → locale.update  POST  /locale/{code}

php artisan test --filter=Phase11AV1Hot4   # → 34 v11A.4 scenarios pass
php artisan test                            # → 374 total scenarios pass

npm ci
npm run typecheck                           # MUST PASS
npm run build                                # MUST SUCCEED
```

## §8 Manual verification

### Catalog gutter

Test `/products` at each width. Use DevTools to inspect the page wrapper:

| Width | Expected `padding-left` on Container | Pass? |
|---|---|---|
| 320px | 16px | ☐ |
| 375px | 16px | ☐ |
| 390px | 16px | ☐ |
| 414px | 16px | ☐ |
| 768px (tablet) | 24px | ☐ |
| 1024px (lg) | 32px | ☐ |
| 1280px+ (xl) | 40px, content centered at 1280px | ☐ |
| large desktop | 40px, large margins outside | ☐ |

Also confirm:
- [ ] Sidebar (Categories) doesn't touch viewport edge at ANY width
- [ ] Desktop sidebar has visible left gutter from viewport
- [ ] Product grid has visible gap from sidebar (gap-6 lg:gap-8 = 24/32px)
- [ ] Mobile (< lg): no aside visible (collapses correctly; categories live in hamburger drawer per v10.6)
- [ ] No body-level horizontal scroll at any width
- [ ] Category names not clipped at any width

### Search suggestions

Open `/` or any storefront page. Click in the search input.

| Test | Expected | Pass? |
|---|---|---|
| Type "a" (1 char) | No dropdown opens | ☐ |
| Type "ab" (2 chars) | Dropdown opens after ~300ms with grouped suggestions | ☐ |
| Type a known partial product name | Matching product appears in Products group | ☐ |
| Type a known category name | Matching category appears in Categories group | ☐ |
| Type a known service name | Matching service appears in Services group | ☐ |
| Type "xyznosuchthing123" | "No suggestions" message displays | ☐ |
| Fast typing (5+ chars in <300ms) | Only one network request fires (debounced) | ☐ |
| Press ArrowDown repeatedly | Selection walks through items (visible highlight) | ☐ |
| Press ArrowUp repeatedly | Selection walks back | ☐ |
| Press Enter on highlighted item | Navigates to that item's page | ☐ |
| Press Escape | Dropdown closes; input retains focus | ☐ |
| Click outside the search panel | Dropdown closes | ☐ |
| Click "View all results for X" | Navigates to `/products?q=X` | ☐ |
| Press Enter with no selection | Submits to `/products?q=X` (standard search still works) | ☐ |
| Disable JS, submit form | Plain GET /products?q=X works | ☐ |
| Mobile width: tap input | Dropdown appears below input, full-width | ☐ |

### Arabic localization

Open `/` as guest, then as customer (`customer@marketplace.test` / `password`):

1. [ ] Site loads in English (default `app.locale = 'en'`, `<html dir="ltr">`)
2. [ ] Language selector shows EXACTLY two options: `English` and `العربية`
3. [ ] Click `العربية`
4. [ ] Page reloads with Arabic interface labels
5. [ ] `<html lang="ar" dir="rtl">` (inspect via DevTools)
6. [ ] Layout becomes RTL — search icon on right side, account icons on left, mobile hamburger position swaps
7. [ ] Navigate to `/products` — catalog still renders, sidebar still has gutter, labels in Arabic
8. [ ] Click into a product (Arabic title may stay if not translated in DB — that's expected per dev §5 dynamic content rule)
9. [ ] Navigate to `/cart` (as customer)
10. [ ] Refresh browser (Ctrl+R) — Arabic persists (session cookie)
11. [ ] Logout → log back in → Arabic persists if stored on user (or revert to session default — both are acceptable)
12. [ ] No blank page at any point
13. [ ] Click `English` in the selector
14. [ ] LTR returns; interface back to English
15. [ ] Confirm only ONE Arabic option appeared throughout (NOT two)

## §16 Contrast verification (catalog sidebar specifically)

Use DevTools → Elements → Computed → Accessibility. Walk through the catalog sidebar:

| Element | Foreground → Background | Required | Verified ratio | Pass? |
|---|---|---|---|---|
| Category label (active) | `text-indigo-700` → `bg-indigo-50` | ≥ 4.5 | ~7:1 | ☐ |
| Category label (inactive) | `text-slate-600` → white | ≥ 4.5 | ~7:1 | ☐ |
| Category count badge | `text-slate-600` → white | ≥ 4.5 | ~7:1 | ☐ |
| Title parenthetical "(24)" | `text-slate-600` → white | ≥ 4.5 | ~7:1 | ☐ |
| Empty state | `text-slate-700` → white | ≥ 4.5 | ~9.2:1 | ☐ |
| Strikethrough original price | `text-slate-600` → white | ≥ 4.5 | ~7:1 (line-through provides de-emphasis) | ☐ |
| Disabled pagination | `text-slate-400` → white | exempt | ~3.5:1 (WCAG disabled exemption) | ☐ |
| Search input placeholder | `text-slate-500` → bg-slate-50 | exempt | ~4.5:1 | ☐ |
| Footer link | `text-slate-400` → `bg-brand-ink` | ≥ 4.5 | ~7:1 (AAA on dark bg) | ☐ |

## §13 Package integrity

```bash
sha256sum -c marketplace-phase-11A-v11A.4-catalog-search-arabic.tar.gz.sha256

mkdir /tmp/v11ah4-verify
tar -xzf marketplace-phase-11A-v11A.4-catalog-search-arabic.tar.gz -C /tmp/v11ah4-verify

EX=/tmp/v11ah4-verify/marketplace

# Catalog gutter
grep -q "<Container className=\"py-6" $EX/resources/js/Pages/Catalog/Index.tsx && echo "✓ Catalog Container wrap"

# Search suggestions
[ -f $EX/app/Http/Controllers/SearchSuggestionController.php ] && echo "✓ Suggestion controller"
[ -f $EX/resources/js/Components/common/SearchBar.tsx ] && echo "✓ SearchBar frontend"
grep -q "search/suggestions" $EX/routes/web.php && echo "✓ Suggestion route"

# Localization
python3 -c "
import json
en = json.load(open('$EX/lang/en.json'))
ar = json.load(open('$EX/lang/ar.json'))
print('✓ en+ar parity' if set(en) == set(ar) and len(en) >= 240 else '✗ parity fail')
"
grep -q "DISPLAY_LOCALES" $EX/resources/js/Components/common/LangSwitcher.tsx && echo "✓ LangSwitcher dedup"

# Tests
[ -f $EX/tests/Feature/Phase11AV1Hot4RegressionTest.php ] && echo "✓ v11A.4 tests"

# v11A.4 VERSION
[ "$(cat $EX/VERSION)" = "Phase 11A v11A.4" ] && echo "✓ VERSION"
```

## CI verdict

```
✅ Phase 11A v11A.4 PASSES — catalog gutter + contrast + search suggestions + Arabic localization
```

## What v11A.4 explicitly does NOT include

- Phase 11B recommendation engine (per dev directive: deferred)
- Match-text highlighting in suggestions (dev §4 said "where practical"; deferred to v11A.5 if needed)
- Recent-search history (the controller does not store per-user history; trivial to add via session if dev wants)
- Translation of admin/vendor surfaces (dev §5 scope was customer storefront)
- Translation of dynamic database content (product names, vendor names — per dev §5 dynamic content rule)
- Migration of catalog `ProductCardView` to use v11A `ProductCard` primitive (separate scope)

## Phase 11A v11A.4 STOPS HERE

No Phase 11B work begun. Pending dev verification at every checklist item above.
