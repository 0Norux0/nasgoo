# Phase 11A v11A.5 — Developer Verification Checklist

Per dev §18 + §22 + §19 + §13.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-11A-v11A.5-complete-arabic-contrast.tar.gz.sha256
tar -xzf marketplace-phase-11A-v11A.5-complete-arabic-contrast.tar.gz --strip-components=1 --overwrite
```

## §22 Required commands

```bash
# Bust v11A.4 cache
php artisan optimize:clear
php artisan cache:forget marketplace:top_categories:v1   # v11A.5 bumps cache key to v2

# Apply the new idempotent backfill migration
php artisan migrate:status                                # confirms 2026_06_24_000001 is pending
php artisan migrate                                       # applies it (safe, idempotent, preserves admin edits)

# Confirm routes
php artisan route:list | grep -i locale                  # → locale.update
php artisan route:list | grep -i search                  # → search.suggestions

# Build
rm -rf public/build/
rm -rf node_modules/.vite/
npm ci
npm run typecheck                                         # MUST PASS
npm run build                                             # MUST SUCCEED

# Run the new audit command
php artisan translations:audit ar
php artisan translations:audit ar -v                      # detailed missing per group

# Tests
php artisan test --filter=Phase11AV1Hot5                  # → 32 v11A.5 scenarios
php artisan test                                           # → 406 total scenarios pass
```

## Expected `php artisan translations:audit ar` output

```
Phase 11A v11A.5 — translation audit for locale: ar
────────────────────────────────────────────────────────────

Interface translation keys (lang/*.json)
  en.json:       325 keys
  ar.json:       325 keys
  Missing in ar: 0 keys

Category name translations
  Total categories: 13 (or your seeded count)
  Missing ar:       0  (after backfill migration runs)

Product name translations (published only)
  Total published products: N
  Missing ar:               N  (vendor-entered content; expected per dev §5)

────────────────────────────────────────────────────────────
Audit summary for ar:
  Total items checked:  ≥338
  Missing translations: N (interface=0, vendor products=variable)
  Coverage:             100% interface; variable for vendor content
```

## §18 Manual Arabic verification

### Homepage (as guest, then as `customer@marketplace.test`)

1. [ ] Open `/` — site loads in English (default `app.locale = 'en'`)
2. [ ] Language selector shows EXACTLY `English` and `العربية` (one Arabic option only)
3. [ ] Click `العربية`
4. [ ] Header utility bar translates (Welcome to..., Global shipping available, Help)
5. [ ] Header search placeholder is in Arabic (ابحث عن منتجات…)
6. [ ] Header user cluster icons titled in Arabic (Wishlist → قائمة الرغبات, Cart → السلة)
7. [ ] Secondary nav translates (Products → المنتجات, Services → الخدمات, Deals → العروض)
8. [ ] Hero eyebrow, headline, subtitle, both CTAs all translate
9. [ ] Hero trust indicators translate (Secure checkout, Verified vendors, Customer support)
10. [ ] Hero illustration card translates (Featured today, Premium selection, Across X categories, View all →)
11. [ ] Trust badges section translates (Secure purchasing / Verified vendors / Flexible delivery / Real human support — titles AND bodies)
12. [ ] Categories section eyebrow + title + subtitle translate; **category names in cards appear in Arabic** (الإلكترونيات, أزياء, المنزل, الجمال, رياضة)
13. [ ] Featured products section translates; products with `name_translations.ar` show Arabic; others show canonical English (controlled fallback)
14. [ ] Deals banner translates (Today's offers, Save big on selected products, etc.) — **subtitle no longer has muted /80 opacity**
15. [ ] Services section translates (Beyond products, Book trusted services, etc.)
16. [ ] How it works translates (Simple to use, 3-step process)
17. [ ] Become-a-vendor section translates
18. [ ] Footer translates (intro, Customer column, Marketplace column, vendor column, copyright line, secure-payments/buyer-protection micro-row)
19. [ ] `<html lang="ar" dir="rtl">` (inspect via DevTools)
20. [ ] Layout becomes RTL — search icon on right side, nav items right-to-left order

### Catalog (`/products`)

21. [ ] Page title "All products" → "جميع المنتجات"
22. [ ] Sidebar "Categories" → "الفئات"
23. [ ] Sidebar "All products" link → "جميع المنتجات"
24. [ ] **Category names in sidebar appear in Arabic** (matches default seed translations)
25. [ ] Sort dropdown options translate (Featured first → المميّزة أولًا, Newest → الأحدث, Price: low to high → السعر: من الأقل إلى الأعلى, etc.)
26. [ ] Sort by label "Sort by:" → "ترتيب حسب:"
27. [ ] Empty state "No products match your filters" → "لا توجد منتجات تطابق الفلاتر."
28. [ ] Featured pill on cards → "مميّز"
29. [ ] ProductCard "Out of stock" → "غير متوفر"
30. [ ] "by {vendor}" line → "بواسطة {vendor}"
31. [ ] Container responsive gutter still applies (sidebar not flush against edge)
32. [ ] Search suggestions panel displays Arabic group labels (Products → المنتجات, Categories → الفئات, Services → الخدمات)
33. [ ] "View all results for X" → "عرض كل النتائج لـ X"

### Product/service detail

34. [ ] Interface controls (description, specs, reviews tabs) — **translation KEYS exist in lang/ar.json, UI WIRING is acknowledged gap deferred to next iteration**
35. [ ] Product title displays Arabic IF `name_translations.ar` is populated; otherwise canonical English fallback
36. [ ] Service title same behavior

### Cart and checkout

37. [ ] Translation keys exist (`cart.title`, `checkout.title`, `cart.checkout`, `cart.total`, etc.) — **UI wiring deferred; acknowledged gap**
38. [ ] Cart prices and totals remain numerically correct (no calculation regression)
39. [ ] RTL layout is usable for any English fallback labels
40. [ ] Order placement is unaffected (regression covered by existing v8/v9 tests)

### Persistence

41. [ ] Refresh page (Ctrl+R) — Arabic persists via session
42. [ ] Navigate between Homepage → Products → back — Arabic persists
43. [ ] Logout → log back in (`customer@marketplace.test`) — Arabic persists
44. [ ] No blank page or 500 error at any point
45. [ ] Switch to English — LTR returns, interface back to English
46. [ ] Confirm ONLY ONE Arabic option appeared throughout (Urdu hidden from selector by DISPLAY_LOCALES filter)

## §19 Contrast verification

Use DevTools → Lighthouse or axe on Homepage in BOTH English and Arabic.

### Hero section (gradient background)

| Element | Foreground / Background | Required | Verified | Pass? |
|---|---|---|---|---|
| Eyebrow badge | `text-accent-200` on `bg-white/10` on brand-800 gradient | ≥ 4.5 | ~9:1 | ☐ |
| H1 headline | `text-white` on brand-800 gradient | ≥ 4.5 | 12+:1 | ☐ |
| Hero paragraph | `text-brand-100` on brand-900 | ≥ 4.5 | ~8.5:1 | ☐ |
| CTA primary | `text-white` on `bg-brand-700` | ≥ 4.5 | ~9:1 | ☐ |
| Trust labels | `text-brand-100` on brand-900 | ≥ 4.5 | ~8.5:1 | ☐ |

### Trust indicators + sections (white background)

| Element | Foreground / Background | Required | Verified | Pass? |
|---|---|---|---|---|
| Trust badge title | `text-slate-900` on white | ≥ 4.5 | 18:1 | ☐ |
| Trust badge body | `text-slate-600` on white | ≥ 4.5 | ~7:1 | ☐ |
| Section eyebrow | `text-accent-700` on white | ≥ 4.5 | ~5.5:1 | ☐ |
| Section title | `text-slate-900` on white | ≥ 4.5 | 18:1 | ☐ |
| Section subtitle | `text-slate-600` on white | ≥ 4.5 | ~7:1 | ☐ |

### Deals banner (gold gradient) — **the v11A.5 fix**

| Element | Foreground / Background | BEFORE | AFTER | Pass? |
|---|---|---|---|---|
| Deals subtitle | `text-gold-900/80` on `bg-gold-500` | ~4:1 (opacity reduces) | **~7:1** (`text-gold-900`, no opacity) | ☐ |
| Deals title | `text-gold-950` on `bg-gold-500` | 10+:1 (no change) | 10+:1 | ☐ |
| Deals eyebrow | `text-gold-900` on `bg-gold-100` | ~9:1 (no change) | ~9:1 | ☐ |

### Footer (dark brand-ink background)

| Element | Foreground / Background | Required | Verified | Pass? |
|---|---|---|---|---|
| Footer intro | `text-slate-400` on `bg-brand-ink` | ≥ 4.5 | ~7:1 (dark bg AAA) | ☐ |
| Footer column titles | `text-white` on `bg-brand-ink` | ≥ 4.5 | 16:1 | ☐ |
| Footer links | `text-slate-400` hover:white on `bg-brand-ink` | ≥ 4.5 | ~7:1 | ☐ |
| Footer copyright | `text-slate-300` on `bg-brand-ink` | ≥ 4.5 | ~11.6:1 | ☐ |

### Catalog page

| Element | Foreground / Background | Required | Verified | Pass? |
|---|---|---|---|---|
| Sidebar category label (active) | `text-indigo-700` on `bg-indigo-50` | ≥ 4.5 | ~7:1 | ☐ |
| Sidebar category label (inactive) | `text-slate-600` on white | ≥ 4.5 | ~7:1 | ☐ |
| Category count badge | `text-slate-600` on white | ≥ 4.5 | ~7:1 (v11A.4) | ☐ |
| Title parenthetical "(24)" | `text-slate-600` on white | ≥ 4.5 | ~7:1 (v11A.4) | ☐ |
| Strikethrough original price | `text-slate-600` on white | ≥ 4.5 | ~7:1 (v11A.4) | ☐ |

### Both languages

| Surface | English LTR | Arabic RTL |
|---|---|---|
| Homepage hero | ☐ readable | ☐ readable |
| Catalog page | ☐ readable | ☐ readable |
| Cart page | ☐ readable | ☐ readable |

## §13 Package integrity

```bash
sha256sum -c marketplace-phase-11A-v11A.5-complete-arabic-contrast.tar.gz.sha256

mkdir /tmp/v11ah5-verify
tar -xzf marketplace-phase-11A-v11A.5-complete-arabic-contrast.tar.gz -C /tmp/v11ah5-verify

EX=/tmp/v11ah5-verify/marketplace

# v11A.5 changes
grep -c "translatedName()" $EX/app/Http/Controllers/HomeController.php && echo "HomeController wired"
grep -c "translatedName()" $EX/app/Http/Controllers/CatalogController.php && echo "CatalogController wired"
grep -c "translatedName()" $EX/app/Http/Controllers/ServiceCatalogController.php && echo "ServiceCatalogController wired"
grep -c "translatedName()" $EX/app/Http/Controllers/SearchSuggestionController.php && echo "SearchSuggestionController wired"
grep -q "top_categories:v2" $EX/app/Http/Middleware/HandleInertiaRequests.php && echo "✓ Cache v2"
[ -f $EX/app/Console/Commands/TranslationsAuditCommand.php ] && echo "✓ Audit command"
ls $EX/database/migrations/*backfill_arabic_category_translations.php && echo "✓ Backfill migration"

# Translation parity
python3 -c "
import json
en = json.load(open('$EX/lang/en.json'))
ar = json.load(open('$EX/lang/ar.json'))
print(f'✓ en={len(en)}, ar={len(ar)}, parity={set(en)==set(ar)}')
"

# Frontend pages
for F in Catalog/Index.tsx Components/ui/v11/ProductCard.tsx Pages/Services/Index.tsx; do
  P="$EX/resources/js/$F"
  grep -q "import { useT }" "$P" && grep -q "const t = useT()" "$P" && echo "✓ $F"
done

# Contrast fix
! grep -q "text-gold-900/80" $EX/resources/js/Pages/Welcome.tsx && echo "✓ Deals subtitle /80 opacity removed"

# Tests
[ -f $EX/tests/Feature/Phase11AV1Hot5RegressionTest.php ] && echo "✓ v11A.5 tests"

# VERSION
[ "$(cat $EX/VERSION)" = "Phase 11A v11A.5" ] && echo "✓ VERSION"
```

## CI verdict

```
✅ Phase 11A v11A.5 PASSES — complete Arabic localization + remaining homepage contrast
```

## What v11A.5 explicitly does NOT include (honest gaps)

- **Vendor admin Filament forms** for entering name_translations.ar (separate scope)
- **Product detail page** UI wiring of new translation keys (keys defined; TSX wiring deferred)
- **Cart/checkout UI** wiring of new translation keys (keys defined; TSX wiring deferred)
- **Admin/vendor backend translations** (out of customer-storefront scope)
- **Laravel validation.php translations** (separate scope — `lang/ar/validation.php`)
- **Vendor product titles** without `name_translations.ar` (per dev §5: do not fabricate)
- **No Phase 11B recommendation engine** (per dev directive)

## Rollback instructions

If v11A.5 needs reverting:

```bash
# 1. Extract v11A.4 archive over the workspace
tar -xzf marketplace-phase-11A-v11A.4-catalog-search-arabic.tar.gz --strip-components=1 --overwrite

# 2. The new backfill migration won't be reverted by tar — it stays harmless on disk;
#    Laravel won't re-run a completed migration. If you want to fully remove it:
rm database/migrations/2026_06_24_000001_backfill_arabic_category_translations.php
# (no migrate:rollback needed — the migration's down() is a no-op)

# 3. The new audit command won't be reverted by tar — remove if desired:
rm app/Console/Commands/TranslationsAuditCommand.php

# 4. Restore v11A.4 cache state (if v2 was populated):
php artisan cache:forget marketplace:top_categories:v2

# 5. Re-run optimize + tests
php artisan optimize:clear
php artisan test --filter=Phase11AV1Hot4
npm run build
```

## Phase 11A v11A.5 STOPS HERE

No Phase 11B work begun. Pending dev verification per §18 manual walkthrough + audit command output + Lighthouse contrast in both languages.
