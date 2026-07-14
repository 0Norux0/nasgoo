# Phase 11B.1 — Developer Verification Checklist

Per dev §30 manual + §32 commands + §31 performance acceptance.

## Pre-deployment baseline

```bash
# Confirm Phase 11A is preserved
sha256sum -c marketplace-phase-11A-final-approved.tar.gz.sha256
# Expected: d96e0758a33402b27f3a9253c89ab747775875ff17d48fc74e42dbd407f4fb59

# Recommended Git tag (run BEFORE applying v11B.1)
git tag phase-11A-final-approved
git push --tags
```

## Deploy

```bash
cd /var/www/marketplace

# Verify v11B.1 archive integrity
sha256sum -c marketplace-phase-11B-1-smart-search.tar.gz.sha256

# Extract over the working copy (preserves /storage, /vendor, /node_modules, .env)
tar -xzf marketplace-phase-11B-1-smart-search.tar.gz --strip-components=1 --overwrite
```

## §32 Required commands

```bash
# Bust cache
php artisan optimize:clear
rm -rf public/build/
rm -rf node_modules/.vite/

# Migration status — should show 4 new pending v11B.1 migrations
php artisan migrate:status | grep 2026_06_25

# Apply migrations (all additive, idempotent, MySQL-compatible)
php artisan migrate

# Verify new tables created
php artisan tinker --execute="echo Schema::hasTable('search_synonyms') ? 'OK ' : 'MISSING ';"
php artisan tinker --execute="echo Schema::hasTable('search_queries') ? 'OK ' : 'MISSING ';"
php artisan tinker --execute="echo Schema::hasTable('user_recent_searches') ? 'OK ' : 'MISSING ';"

# Routes
php artisan route:list | grep -i search
# Expected: search.suggestions, search.recent.destroy

# Build
npm ci
npm run typecheck                       # MUST PASS
npm run build                            # MUST SUCCEED

# Tests
php artisan test --filter=Phase11B1     # 53 v11B.1 scenarios
php artisan test                         # 459 total scenarios

# Translation audit still works
php artisan translations:audit ar
```

## Optional: fresh-DB smoke test (separate test DB only!)

```bash
# Per dev §32 — only run against a separate test DB, NEVER prod
DB_DATABASE=marketplace_test php artisan migrate:fresh --seed
```

## §30 Manual verification — English

Test as **guest** first, then as `customer@marketplace.test`.

### Exact product name search

1. [ ] Open `/`
2. [ ] Click the header search bar
3. [ ] Confirm dropdown opens immediately showing popular + recent groups (if any data)
4. [ ] Type the exact name of a known product
5. [ ] Confirm that product is the FIRST result in the Products group
6. [ ] Confirm group caps (≤5 products, ≤3 categories, ≤3 services)

### Partial product name + relevance check

7. [ ] Search for a partial term that matches multiple products
8. [ ] Confirm the product whose title CONTAINS the search appears
9. [ ] Confirm a popular-but-unrelated product does NOT outrank a title-matching one

### Category search

10. [ ] Search for a category name (e.g. "Electronics")
11. [ ] Confirm category appears in the Categories group of the dropdown
12. [ ] Click category — confirm `/products?category=…` opens

### Service search

13. [ ] Search for a service name
14. [ ] Confirm service appears in the Services group

### Vendor search (via catalog filter)

15. [ ] Open `/products?vendor=vendor-slug`
16. [ ] Confirm the catalog shows only that vendor's products
17. [ ] Confirm Active filter chip appears with vendor name
18. [ ] Click × on the chip — confirm filter removed

### Misspelled query — "Did you mean?"

19. [ ] Search for a slightly misspelled known term (e.g. "elektronics" if Electronics is a category)
20. [ ] In the dropdown, confirm the "Did you mean: electronics?" banner appears
21. [ ] Click the banner — confirm input is replaced with the corrected query

### Synonym test

22. [ ] As admin, add a synonym pair via Tinker: `\App\Models\SearchSynonym::create(['locale'=>'en','term'=>'mobile','synonym'=>'phone','is_active'=>true])`
23. [ ] Bust the synonym cache: `\App\Services\Search\SynonymService::class | app | flush`
24. [ ] Search for "mobile" — confirm products titled "phone" also appear
25. [ ] Search for "phone" — confirm products titled "mobile" also appear

### No-result query

26. [ ] Search for nonsense gibberish ("xyz123-nonsense")
27. [ ] Confirm dropdown shows empty state or did-you-mean
28. [ ] Open `/products?q=xyz123-nonsense`
29. [ ] Confirm catalog shows the "No products match your search" panel
30. [ ] Confirm query echo "No results for 'xyz123-nonsense'" appears
31. [ ] Confirm Clear All link works (if filters were also active)

### Every sort option

32. [ ] On `/products?q=test`, cycle through every sort dropdown option:
   - Most relevant (default when q set)
   - Newest
   - Featured first
   - Price: low to high
   - Price: high to low
   - Highest rated
   - Most popular
   - Best selling
33. [ ] Confirm each sort changes the order
34. [ ] Confirm sort is preserved across pagination
35. [ ] Confirm sort change preserves all other filters

### Filter combinations

36. [ ] Apply category filter
37. [ ] Add price range (e.g. 10-100 KWD)
38. [ ] Add rating filter (4 stars and above)
39. [ ] Toggle In Stock
40. [ ] Toggle On Sale
41. [ ] Confirm all 5 active chips appear at the top
42. [ ] Confirm catalog results respect ALL filters (AND logic)
43. [ ] Confirm facet counts on sidebar reflect the current scope

### Pagination

44. [ ] On a filtered catalog, click page 2
45. [ ] Confirm URL preserves all filters in query string
46. [ ] Confirm browser Back button returns to page 1 with filters intact
47. [ ] Copy the URL, open in new tab — confirm same filtered results

### Mobile suggestions

48. [ ] Resize to 375px (or use mobile device)
49. [ ] Tap header search icon to open mobile drawer
50. [ ] Type 2+ characters
51. [ ] Confirm dropdown fits viewport without horizontal overflow
52. [ ] Tap a suggestion — confirm navigation

### Keyboard navigation

53. [ ] Tab to the search input
54. [ ] Type 3 characters
55. [ ] Press Arrow Down — confirm first suggestion highlights
56. [ ] Continue Arrow Down — confirm cycling through groups
57. [ ] Press Enter on a highlighted item — confirm navigation
58. [ ] Press Escape — confirm dropdown closes

## §30 Manual verification — Arabic

Switch language to `العربية`. Repeat as **guest** then **customer**.

### Arabic product/category/service search

59. [ ] Search for an Arabic product title (where `name_translations.ar` is set)
60. [ ] Confirm product appears in dropdown
61. [ ] Search for an Arabic category name (e.g. "إلكترونيات")
62. [ ] Confirm category appears
63. [ ] Click — confirm landing on the category page with Arabic labels

### Arabic suggestions

64. [ ] Type Arabic characters in the search bar
65. [ ] Confirm dropdown labels are Arabic ("المنتجات", "الفئات", "الخدمات", "عمليات بحث شائعة", "عمليات بحثك الأخيرة")
66. [ ] Confirm "هل تقصد:" banner appears for typos

### RTL filters

67. [ ] Open `/products` in Arabic
68. [ ] Confirm sidebar is on the right (RTL)
69. [ ] Confirm filter section headers translate ("الفلاتر", "السعر", "التقييم", "متوفر", "في العروض")
70. [ ] Confirm active-filter chips render correctly in RTL with × on the correct side

### Arabic active chips

71. [ ] Apply price + rating filters in Arabic mode
72. [ ] Confirm chip labels show "≥ 10", "★ 4+", "متوفر" etc.
73. [ ] Click × on a chip — confirm correct filter removed

### Arabic no-result state

74. [ ] Open `/products?q=nonsenseاراب`
75. [ ] Confirm "لا توجد نتائج لـ ..." appears
76. [ ] Confirm "جرّب كلمات مختلفة..." body text appears

### Arabic sorting

77. [ ] Open the sort dropdown in Arabic
78. [ ] Confirm all options are in Arabic: "الأكثر صلة", "الأحدث", "الأعلى تقييمًا", "الأكثر مبيعًا", etc.

### English fallback for untranslated vendor content

79. [ ] Search for a vendor product that has no `name_translations.ar`
80. [ ] Confirm the English title still appears (controlled fallback from v11A.5)

## §30 User states

| State | Recent searches | Popular searches | Did-you-mean | Filters |
|---|---|---|---|---|
| Guest | ☐ empty (never shown) | ☐ visible | ☐ visible | ☐ all work |
| Customer | ☐ visible (own only) | ☐ visible | ☐ visible | ☐ all work |
| Vendor | ☐ visible (own as user) | ☐ visible | ☐ visible | ☐ all work |
| Admin | ☐ visible (own as user) | ☐ visible | ☐ visible | ☐ all work |

Critical: confirm **no protected data appears in suggestions** at any role level.

## §31 Performance acceptance

Run a quick measure against a representative product corpus:

```bash
# Time a search
time curl -s "https://your-staging/search/suggestions?q=laptop" > /dev/null

# Time a catalog filter
time curl -s "https://your-staging/products?q=laptop&rating_min=4&in_stock=1" > /dev/null

# Look for N+1 — enable query log temporarily
php artisan tinker --execute="
DB::enableQueryLog();
app(\App\Services\Search\MarketplaceSearchService::class)->products('test', 'en')->limit(24)->get();
echo count(DB::getQueryLog());
"
# Expected: ≤2-3 (the score query + eager loads)
```

| Check | Target | Verified |
|---|---|---|
| No N+1 queries on catalog | ≤5 queries per request | ☐ |
| Suggestions response time | <300ms after debounce | ☐ |
| Facets cached | first request slower, subsequent <50ms | ☐ |
| Search query count | <10 queries per /products | ☐ |
| Result payload size | <100KB | ☐ |

If a query is slow, dev should EXPLAIN it and document. See `PHASE_11B_1_SMART_SEARCH_REPORT.md` §31 for design analysis.

## §35 Package integrity

```bash
mkdir /tmp/v11b1-verify
tar -xzf marketplace-phase-11B-1-smart-search.tar.gz -C /tmp/v11b1-verify

EX=/tmp/v11b1-verify/marketplace

# v11B.1 services exist
ls $EX/app/Services/Search/ | wc -l       # → 5
ls $EX/app/Models/Search* $EX/app/Models/UserRecentSearch.php 2>/dev/null

# v11B.1 migrations
ls $EX/database/migrations/2026_06_25_*.php

# Privacy contract
grep "user_id\|ip_address\|session_id" $EX/database/migrations/2026_06_25_000002_create_search_queries_table.php
# Should appear only in COMMENTS, never as column declarations

# Frontend
grep -q "function Chip(" $EX/resources/js/Pages/Catalog/Index.tsx && echo "✓ Chip defined"
grep -q "catalog-active-chips" $EX/resources/js/Pages/Catalog/Index.tsx && echo "✓ Active chips testid"

# Translations
python3 -c "
import json
en = json.load(open('$EX/lang/en.json'))
ar = json.load(open('$EX/lang/ar.json'))
print(f'en={len(en)}, ar={len(ar)}, parity={set(en)==set(ar)}')"
# Expected: en=352, ar=352, parity=True

# Tests
grep -c "^it(" $EX/tests/Feature/Phase11B1SmartSearchTest.php
# Expected: 53

# VERSION
cat $EX/VERSION
# Expected: Phase 11B.1
```

## CI verdict

```
✅ Phase 11B.1 PASSES — smart search + dynamic filters + relevance ranking
```

## Acceptance checklist

| Criterion | Status |
|---|---|
| Phase 11A baseline preserved | ☐ tagged + archived |
| All v11B.1 services present (5) | ☐ |
| All v11B.1 models present (3) | ☐ |
| All v11B.1 migrations apply cleanly (4) | ☐ |
| Routes registered (suggestions, recent destroy) | ☐ |
| Frontend wired (Chip + active chips + did-you-mean + sidebar filters) | ☐ |
| Translation parity (352 = 352, Arabic real) | ☐ |
| Pest 53 v11B.1 scenarios pass | ☐ |
| No N+1 queries on catalog | ☐ |
| No regression (homepage, cart, checkout, login flows, admin/vendor reports) | ☐ |
| Privacy: search_queries has no user_id/ip/session | ☐ |
| Feature flags allow controlled rollback | ☐ |

## Phase 11B.1 STOPS HERE

No further 11B modules begun. Pending dev verification.
