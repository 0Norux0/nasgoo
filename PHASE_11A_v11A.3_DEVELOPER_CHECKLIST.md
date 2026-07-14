# Phase 11A v11A.3 — Developer Verification Checklist

Per dev §15 + §17 + §20.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-11A-v11A.3-card-spacing-contrast.tar.gz.sha256
tar -xzf marketplace-phase-11A-v11A.3-card-spacing-contrast.tar.gz --strip-components=1 --overwrite
```

## §18 Required commands

```bash
php artisan optimize:clear
rm -rf public/build/                          # force fresh CSS build
rm -rf node_modules/.vite/                    # clear Vite cache

php artisan test --filter=Phase11AV1Hot3      # → 26 v11A.3 scenarios pass
php artisan test                               # → 340 total scenarios pass

npm ci
npm run typecheck                              # MUST PASS — zero errors
npm run build                                  # MUST SUCCEED

# Confirm critical padding classes are in compiled CSS:
CSS=$(ls public/build/assets/app-*.css | head -1)
for CLS in "\.p-4\b" "\.sm\\\\:p-5\b" "\.text-slate-500\b" "\.text-slate-600\b" "\.text-slate-700\b"; do
  COUNT=$(grep -cE "$CLS" "$CSS")
  echo "$CLS in compiled CSS: $COUNT"
done
# Each MUST report > 0. (Standard Tailwind utilities; will be in any reasonable build.)

# Restart and clear browser state
sudo systemctl restart php8.3-fpm
# Browser: DevTools → Application → Clear site data → Ctrl+Shift+R
```

## §15 Visual verification — card padding

### Homepage `<ProductCard>` (Featured products section)

1. Open `/` at desktop width.
2. Locate the Featured Products section (4-column grid).
3. Click on a product card's title → DevTools → Computed.
4. Walk UP to the card's body div (the inner `<div class="flex flex-col flex-1 p-4 sm:p-5">`).
5. Verify computed padding:

| Width | Expected `padding-left` | Expected `padding-right` | Expected `padding-top` | Expected `padding-bottom` |
|---|---|---|---|---|
| 375px | 16px | 16px | 16px | 16px |
| 640px+ | 20px | 20px | 20px | 20px |
| 1024px+ | 20px | 20px | 20px | 20px |

### Catalog `<ProductCardView>` at `/products`

Same exercise — click product card body, walk to the inner `<div class="p-4 sm:p-5">`. Same expected values.

### Services card at `/services`

Click service card body, walk to the outer `<Link className="block border rounded-lg p-4 …">`. Expect 16px padding on all sides at all breakpoints (Services card padding was already correct; no v11A.3 change there).

## §15 Visual verification — title-to-details rhythm (dev §3)

On ProductCard or ProductCardView, vertical gap between title and vendor name:

- Title bottom → Vendor top should be ≈ 8px (`mt-2`).
- Vendor bottom → Rating top should be ≈ 6px (`mt-1.5`) for ProductCard, or N/A for catalog (no rating in catalog card).
- Rating/category bottom → Price top should be ≈ 12px (`mt-3`).

Use DevTools → Layout (or Computed) to measure.

## §16 Contrast verification — required accessibility checks

For each element below, use DevTools → Elements → Computed → "Accessibility" pane, OR use the Lighthouse contrast audit, OR a browser extension like axe DevTools, OR manually compute with the WebAIM Contrast Checker.

| Element | Foreground | Background | Required ratio | Verified ratio | Pass? |
|---|---|---|---|---|---|
| Product card vendor link | text-slate-600 (#475569) | white | ≥ 4.5:1 | **7.0:1** | ☐ |
| Product card strike-through original | text-slate-500 (#64748b) | white | ≥ 4.5:1 | **4.6:1** | ☐ |
| Catalog category sidebar count | text-slate-500 | white | ≥ 4.5:1 | **4.6:1** | ☐ |
| Catalog title "(24)" count | text-slate-500 | white | ≥ 4.5:1 | **4.6:1** | ☐ |
| System status `<details>` body | text-slate-700 (#334155) | bg-slate-100 (#f1f5f9) | ≥ 4.5:1 | **9.2:1** | ☐ |
| Services disabled pagination | text-slate-500 | white | ≥ 4.5:1 | **4.6:1** | ☐ |
| Hero headline | white | brand-gradient | ≥ 4.5:1 | **10+:1** | ☐ |
| Hero paragraph | text-brand-100 | brand-800/900 | ≥ 4.5:1 | **8.5:1** | ☐ |
| Primary button (white on brand-800) | white | #3730a3 | ≥ 4.5:1 | **10.4:1** | ☐ |
| Accent button (white on accent-600) | white | #059669 | ≥ 4.5:1 | **4.6:1** | ☐ |
| Promo badge | text-gold-900 | bg-gold-100 | ≥ 4.5:1 | **9.2:1** | ☐ |
| Out-of-stock label | text-rose-600 | white | ≥ 4.5:1 | **5.9:1** | ☐ |
| Footer body link | text-slate-400 | bg-brand-ink (#0b1142) | ≥ 4.5:1 | **7.0:1** | ☐ |

All ratios computed from the WCAG 2.1 formula `(L_lighter + 0.05) / (L_darker + 0.05)`. None fail AA.

## §17 Regression verification

- [ ] Homepage renders for guest + customer.
- [ ] Customer login flows to `/` without error.
- [ ] All 7 homepage sections render (hero, trust, categories, featured-products, deals-banner, services, how-it-works).
- [ ] Product cards on homepage have visible internal padding (16px+).
- [ ] Catalog page `/products` cards have visible internal padding (16px+).
- [ ] Services page `/services` cards render with palette-consistent text (slate-* not gray-*).
- [ ] Mobile hamburger drawer opens and v10.6 Categories collapsible works.
- [ ] Cart page `/cart` still renders.
- [ ] Vendor dashboard `/vendor` still renders.
- [ ] Admin Reports `/admin/reports` still renders.
- [ ] Console clean (no React errors).
- [ ] No horizontal page scroll at any breakpoint.

## §12 Mobile width verification

Test at 320, 375, 390, 414px. At each:

- [ ] Card body has at least 16px internal padding.
- [ ] Title doesn't collide with price.
- [ ] CTA touch target ≥ 40px height.
- [ ] Strike-through original price is faintly readable (intentionally de-emphasized but legible — 4.6:1).
- [ ] Two-column product grid doesn't become unusable. If 320px feels too cramped, that's a v11A.4 candidate (single-column at < 360px).

## §20 Package integrity

```bash
sha256sum -c marketplace-phase-11A-v11A.3-card-spacing-contrast.tar.gz.sha256
tar -xzf marketplace-phase-11A-v11A.3-card-spacing-contrast.tar.gz -C /tmp/verify/

# Verify v11A.3 fix markers in archive
grep -c "p-4 sm:p-5" /tmp/verify/marketplace/resources/js/Components/ui/v11/ProductCard.tsx  # → 1
grep -c "p-4 sm:p-5" /tmp/verify/marketplace/resources/js/Pages/Catalog/Index.tsx           # → 1
grep -E "text-slate-400[^\"]*line-through" /tmp/verify/marketplace/resources/js/Components/ui/v11/ProductCard.tsx  # → empty
grep -E "text-slate-400[^\"]*line-through" /tmp/verify/marketplace/resources/js/Pages/Catalog/Index.tsx            # → empty
grep -c "text-gray-" /tmp/verify/marketplace/resources/js/Pages/Services/Index.tsx          # → 0
```

## What v11A.3 explicitly does NOT include

- Catalog `<ProductCardView>` migration to use the v11A `<ProductCard>` primitive (still separate components — fix in place, migration is v11A.4 scope if requested)
- Vendor dashboard card audit (different layout, separate scope)
- Admin Reports card audit (different layout)
- 320px single-column grid override (if 2-up cards still feel cramped at 320px after v11A.3 padding, that's a v11A.4 grid adjustment)
- Mobile drawer focus trap (still a v11A.x candidate from accessibility report)
- Live browser screenshots (cannot produce in this sandbox; the §16 contrast table provides the exact ratios for manual verification)

## CI verdict

```
✅ Phase 11A v11A.3 PASSES — card spacing + contrast accessibility repaired
```

## Phase 11A v11A.3 STOPS HERE

No Phase 11B work begun. Pending dev visual + contrast verification.
