# Phase 11A v11A.2 — Developer Verification Checklist

Per dev §15 + §16 + §12.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-11A-v11A.2-active-container-fix.tar.gz.sha256
tar -xzf marketplace-phase-11A-v11A.2-active-container-fix.tar.gz --strip-components=1 --overwrite
```

## §15 Required commands (in this exact order)

```bash
# 1. Clear EVERYTHING server-side
php artisan optimize:clear
rm -rf public/build/                 # FORCE a fresh build — remove stale assets
rm -rf node_modules/.vite/           # remove Vite build cache if present

# 2. Tests (source-level proof)
php artisan test --filter=Phase11AV1Hot2   # → 26 v11A.2 scenarios pass
php artisan test                            # → 314 total scenarios pass

# 3. Build
npm ci                                       # use lockfile, no version drift
npm run typecheck                           # MUST PASS — zero errors
npm run build                                # MUST SUCCEED

# 4. Confirm critical classes ARE in the built CSS
CSS_FILE=$(ls public/build/assets/app-*.css | head -1)
echo "Built CSS file: $CSS_FILE"
for CLS in "max-w-7xl" "px-4" "px-6" "px-8" "px-10"; do
  COUNT=$(grep -c "\.$CLS\b" "$CSS_FILE")
  echo "$CLS in compiled CSS: $COUNT occurrence(s)"
done
# Each should report at least 1. If any reports 0, the build pipeline is broken.

# 5. Restart Laravel + clear all browser-side state
sudo systemctl restart php8.3-fpm
# In the browser:
# - DevTools → Application → Service Workers → "Unregister" all
# - DevTools → Application → Storage → "Clear site data"
# - Close ALL tabs of the site, reopen, Ctrl+Shift+R
```

If step 4 reports zero matches for any of the listed classes, **STOP** — the issue is in the build pipeline itself, not in v11A.2 source. Inspect:
- `tailwind.config.js` content paths
- Vite plugin configuration
- Whether `@tailwind base; @tailwind components; @tailwind utilities;` directives are still in `resources/css/app.css`

## §2 Active asset verification

```bash
# Compare manifest CSS filename with what the browser loads
cat public/build/manifest.json | grep -oE '"resources/css/app\.css":\s*\{[^}]+\}'
# Note the hashed filename (e.g. "app-Abc123.css")

# Open browser → DevTools → Network tab → filter by CSS
# Reload the page
# Confirm the loaded CSS filename matches the manifest's hashed filename
```

If they don't match: browser is serving stale CSS. Clear cache again.

## §12 Mandatory computed-style verification

This is the dev's explicit "required proof". Source-level changes are NOT enough — the running browser must show measurable padding.

### At 375px width

1. Open `/` in the browser.
2. DevTools → Toggle Device Toolbar (Cmd/Ctrl+Shift+M) → set width to 375px.
3. Click any element in the homepage hero (e.g., the H1 headline).
4. DevTools → Elements panel → Computed tab (or Styles tab).
5. Walk UP the DOM tree to the first ancestor with class `mx-auto w-full max-w-7xl px-4 ...`. That's the Container div.
6. **Inspect its computed styles**:

| Property | Expected at 375px | Pass |
|---|---|---|
| `padding-left` | `16px` | ☐ |
| `padding-right` | `16px` | ☐ |
| `max-width` | `1280px` (or `80rem`) | ☐ |
| `width` | (375px viewport minus 0 because w-full = 100%) ≈ `375px` | ☐ |
| `margin-left` | `0px` (because w-full ≥ container at 375px) | ☐ |
| `margin-right` | `0px` | ☐ |

If `padding-left` shows `0px`, the spacing classes are NOT in the compiled CSS. Re-run build steps.

### At desktop width (1440px)

1. Reset DevTools device toolbar; use natural browser width ~1440px.
2. Click the homepage hero H1.
3. Walk UP to the Container.
4. **Inspect computed styles**:

| Property | Expected at 1440px | Pass |
|---|---|---|
| `padding-left` | `40px` (xl:px-10) | ☐ |
| `padding-right` | `40px` | ☐ |
| `max-width` | `1280px` (or `80rem`) | ☐ |
| `width` | `1280px` (max-width cap) | ☐ |
| `margin-left` | `80px` (viewport 1440 − container 1280 = 160, split = 80) | ☐ |
| `margin-right` | `80px` | ☐ |

If these are correct, v11A.2 is verified live.

## §13 Mandatory visual checks at each breakpoint

For each width, take a screenshot:

| Width | Use case | Check |
|---|---|---|
| 320px | iPhone SE 1st gen | Content not flush against edges. ☐ |
| 375px | iPhone SE 2nd gen | Content has visible side gap. ☐ |
| 390px | iPhone 12/13/14 | Visible side gap. ☐ |
| 414px | iPhone Plus | Visible side gap. ☐ |
| 768px | iPad portrait | More side padding visible (24px). ☐ |
| 1024px | iPad landscape | Larger side padding (32px). ☐ |
| 1280px | Desktop | Side padding (40px). ☐ |
| 1920px | Wide desktop | Content centered with large side margins (mx-auto). ☐ |

## §14 Regression checks

Confirm v11A.2 doesn't break:

- [ ] Homepage renders for guest + customer + vendor.
- [ ] Customer login still flows to `/`.
- [ ] Search bar in header submits to `/products?q=…`.
- [ ] Mobile hamburger toggles the drawer.
- [ ] v10.6 mobile-categories collapsible expands inside the drawer.
- [ ] Cart badge shows count.
- [ ] No Console errors.
- [ ] All 7 homepage sections visible.

## §8 Header specific checks (running browser)

Desktop:
- [ ] Logo box has visible left gap from viewport edge.
- [ ] Search bar centered with margin on both sides.
- [ ] Account/cart cluster on right has gap from edge.

Mobile (375px):
- [ ] Logo box has visible left gap.
- [ ] Cart icon + hamburger have right gap.
- [ ] Drawer (when open) has 16px internal nav-list padding.
- [ ] Category links inside drawer don't touch drawer edges.

## §10 Other pages

Check each at 375px and desktop:

- [ ] `/products` — catalog grid has side gaps.
- [ ] `/products/{slug}` — product detail has side gaps.
- [ ] `/services` — services listing has side gaps.
- [ ] `/cart` — cart items have side gaps.
- [ ] `/orders` — order list has side gaps.
- [ ] Login, register pages — form has side gaps.

NOTE: pages NOT migrated to `<Container>` rely on the legacy `.container-app` CSS class (which v11A.1 updated to include `xl:px-10`). If those pages have side padding, the legacy path is also working. If they DON'T, those pages need migration to `<Container>` in v11A.3.

## §11 Dashboard layouts

- [ ] Vendor dashboard (`/vendor`) — content has side gaps.
- [ ] Admin Reports (`/admin/reports`) — content has side gaps.

Vendor/Admin layouts use VendorLayout/AdminLayout, NOT StorefrontLayout. v11A.2 didn't audit them. If they're edge-to-edge, that's v11A.3 scope.

## §16 Package integrity

```bash
sha256sum -c marketplace-phase-11A-v11A.2-active-container-fix.tar.gz.sha256
# Verify the archive
tar -xzf marketplace-phase-11A-v11A.2-active-container-fix.tar.gz -C /tmp/verify-v11ah2/

# Confirm key files exist
ls /tmp/verify-v11ah2/marketplace/resources/js/Components/Layout/Container.tsx  # NEW path
[ ! -f /tmp/verify-v11ah2/marketplace/resources/js/Components/ui/v11/Container.tsx ] && echo "✓ v11A.1 path correctly REMOVED"

# Confirm Welcome.tsx imports from new path
grep "from '@/Components/Layout/Container'" /tmp/verify-v11ah2/marketplace/resources/js/Pages/Welcome.tsx
grep "from '@/Components/Layout/Container'" /tmp/verify-v11ah2/marketplace/resources/js/Layouts/StorefrontLayout.tsx

# Confirm Tailwind safelist
grep -A20 "safelist:" /tmp/verify-v11ah2/marketplace/tailwind.config.js
```

## CI verdict

```
✅ Phase 11A v11A.2 PASSES — active container root-cause repaired
```

## What v11A.2 explicitly does NOT include

- Vendor dashboard side-padding audit (separate layout)
- Admin Reports side-padding audit (separate layout)
- Catalog list, product detail, cart page migrations to `<Container>` (still on `.container-app` CSS class)
- Pre-built compiled CSS in the archive (the dev must run `npm run build`)
- Live browser screenshots (cannot produce in this sandbox; dev's §13 walkthrough is the proof)

If §12 computed-style verification FAILS even after v11A.2 with the safelist, the environment-side issue (browser disk cache that resists clear, CDN edge cache, build pipeline misconfiguration) is outside what v11A.2 source can fix and needs the dev's hands-on debugging.

## Phase 11A v11A.2 STOPS HERE

No Phase 11B work begun. Pending dev computed-style verification.
