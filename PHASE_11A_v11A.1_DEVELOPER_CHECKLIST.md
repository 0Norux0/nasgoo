# Phase 11A v11A.1 — Developer Verification Checklist

Per dev §15 + §17.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-11A-v11A.1-spacing-fix.tar.gz.sha256
tar -xzf marketplace-phase-11A-v11A.1-spacing-fix.tar.gz --strip-components=1 --overwrite
```

## §15 Required commands

```bash
php artisan optimize:clear
php artisan test --filter=Phase11AV1Hot1   # → 20 v11A.1 scenarios pass
php artisan test                            # → 288 total scenarios pass
npm ci
npm run typecheck                           # MUST PASS — zero errors, no @ts-ignore
npm run build                               # MUST SUCCEED
```

Then restart Laravel/PHP-FPM. **Critical:** clear the browser cache + service worker before visual verification (this was a likely contributor to the original v11A spacing report).

```bash
# Browser DevTools → Application → Service Workers → Unregister
# Application → Storage → "Clear site data" → confirm
# Then: Ctrl+Shift+R (hard refresh)
```

Confirm the active assets match the manifest:

```bash
cat public/build/manifest.json | grep -oE '"app-[^"]+\.css"' | head
# Compare with the CSS filename loaded by the browser (Network tab → Filter by CSS).
# They must match. If not, the browser is serving stale assets.
```

## §12 Manual verification at required breakpoints

For each width below, open DevTools → Device toolbar → set width → reload → screenshot.

| Width | Use case | Test |
|---|---|---|
| 320px | iPhone SE | Open homepage. Header: logo + cart + hamburger fit. No horizontal scroll. Mobile drawer opens with 16px internal side padding. |
| 375px | iPhone SE 2nd gen | Hero headline visible. Trust indicators in 2-col grid with side padding. Product grid in 2-col with gutters. |
| 390px | iPhone 12/13/14 | Same as 375 but slightly more breathing room. |
| 414px | iPhone Plus | Brand name should now appear next to logo (sm breakpoint = 640px so still no — confirm logo box visible alone). |
| 768px | iPad portrait | Search bar appears in header. Categories nav row visible? (lg = 1024, so no — but main header has padding). |
| 1024px | iPad landscape | Secondary nav row visible. Trust indicators in 4-col grid. Featured products in 3-col. **32px side padding visible** (lg:px-8). |
| 1280px | Desktop | Featured products in 4-col. **40px side padding (xl:px-10).** Content centered with max-w-7xl. |
| 1920px (large desktop) | Wide desktop | Content stays at 1280px max width, centered. Side margins now ~320px each side from `mx-auto`. The 40px `xl:px-10` is still there inside that. |

For each width, confirm:

- [ ] No body-level horizontal scroll (no scrollbar at bottom).
- [ ] Header logo + brand name NOT touching the left/start edge.
- [ ] Account/cart icons NOT touching the right/end edge.
- [ ] Search bar (where visible) fits inside the header with margin on both sides.
- [ ] Hero headline NOT touching screen edges.
- [ ] Trust indicators NOT touching screen edges.
- [ ] Category cards NOT touching screen edges.
- [ ] Featured products grid NOT touching screen edges.
- [ ] Deals banner: heading + CTA inside the padded container (but the gold background goes full-bleed — intentional).
- [ ] How it works step cards NOT touching screen edges.
- [ ] Become-a-vendor CTA NOT touching screen edges.
- [ ] Footer columns NOT touching screen edges.

## §3 Homepage specific checks

- [ ] All headings share the SAME left edge (visual grid alignment).
- [ ] Card grids inside the container (not bleeding out).
- [ ] Hero background MAY be full-bleed (gradient sweeps edge-to-edge) but the hero text content is padded inside.
- [ ] Deals banner gold gradient sweeps edge-to-edge but heading + CTA are inside the padded container.
- [ ] Footer brand-ink background sweeps edge-to-edge but the 4-column content is inside the padded container.

## §4 Header and navigation

- [ ] Desktop: utility bar (top) has left + right padding for Welcome text and Help/Lang.
- [ ] Desktop: main header logo box has left padding from viewport edge.
- [ ] Desktop: account cluster (avatar + name + dropdown) has right padding.
- [ ] Desktop: secondary nav (Products/Services/Deals/categories/vendor CTA) inside container.
- [ ] Mobile: hamburger button has right padding from viewport edge.
- [ ] Mobile: logo has left padding from viewport edge.
- [ ] Mobile: drawer opens with `px-4` internal padding (16px) on nav block.
- [ ] Mobile: drawer category links do NOT touch drawer edges.
- [ ] Mobile: drawer close (×) button has padding from drawer edges.

## §5–§9 Catalog / product detail / cart / vendor / admin (regression)

These pages were NOT touched in v11A.1 but they DO use the `container-app` CSS class (now updated to include `xl:px-10`). Verify they render with side padding:

- [ ] `/products` catalog: products grid has side padding at all breakpoints.
- [ ] `/products/{slug}` product detail: title + image gallery have side padding.
- [ ] `/cart`: cart items + coupon input have side padding.
- [ ] `/orders`: order list has side padding.
- [ ] `/bookings`: booking list has side padding.

If any of these still show edge-to-edge content, the `container-app` CSS class compilation is the issue and those pages need migration to `<Container>` in a v11A.2 patch. The CI sub-check verifies the CSS contains the new utility — if the dev sees it visually working everywhere, v11A.1 is sufficient.

## §11 Vertical spacing review

- [ ] Section-to-section gaps feel balanced (not too crowded, not too vast).
- [ ] Headings have appropriate margin below before content begins.
- [ ] Cards have internal padding (text doesn't touch card border).

## §16 Regression checks

Confirm v11A surfaces still work:

- [ ] Homepage renders for guest + customer + vendor.
- [ ] Customer login → / still renders, no Console error.
- [ ] Mobile hamburger toggles drawer.
- [ ] Mobile drawer Categories collapsible (v10.6) still expands.
- [ ] Header search submits to `/products?q=…`.
- [ ] Cart badge shows count when items present.
- [ ] Footer 4-column layout renders.
- [ ] Console clean across all of the above.

## CI verdict

```
✅ Phase 11A v11A.1 PASSES — responsive container spacing repaired
```

## What's NOT in v11A.1 (deferred)

- Vendor dashboard side padding audit (uses VendorLayout, not StorefrontLayout)
- Admin Reports side padding audit (uses AdminLayout)
- Catalog/Product detail page-level redesign (Phase 11A.2 if needed)
- Mobile drawer focus trap (v11A.1 candidate from accessibility report, not fixed here)

If the dev finds vendor/admin pages also have the edge-to-edge issue, that's v11A.2 — same fix pattern (migrate to `<Container>`), separate testable release per dev guidance.

## Phase 11A v11A.1 STOPS HERE

No Phase 11B work begun. Pending dev approval of v11A.1 before any smart-intelligence (Phase 11B) features.
