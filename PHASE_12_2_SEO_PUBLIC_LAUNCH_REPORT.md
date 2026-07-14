# Phase 12.2 — SEO & Public Launch Report

Verifies public-facing SEO and launch-day polish. Grounded in actual files (`public/robots.txt`, `app/Http/Controllers/Public/SitemapController.php`, layout components).

## robots.txt

Currently in `public/robots.txt`:

```
User-agent: *
Disallow:
```

This ALLOWS ALL crawlers to index EVERYTHING. Common at launch. The operator may want to add explicit disallows for private paths:

```
User-agent: *
Disallow: /admin/
Disallow: /vendor/
Disallow: /orders/
Disallow: /cart/
Disallow: /checkout/
Disallow: /login
Disallow: /register
Disallow: /forgot-password

Sitemap: https://YOUR_DOMAIN/sitemap.xml
```

For staging or maintenance-mode preview, TEMPORARILY block all crawling:

```
User-agent: *
Disallow: /
```

Do NOT ship the "block all" version to production.

## Sitemap

Controller exists at `app/Http/Controllers/Public/SitemapController.php`. Runtime-generated (not a static file). Register the route in `routes/web.php` if not already:

```bash
$ grep "SitemapController\|sitemap" routes/web.php
```

If the route exists, the sitemap is served at `/sitemap.xml`. Verify at runtime:

```bash
curl -sI https://YOUR_DOMAIN/sitemap.xml | head -1
# Expected: HTTP/2 200 with Content-Type: application/xml
```

If the route is NOT registered, add:

```php
Route::get('/sitemap.xml', [\App\Http\Controllers\Public\SitemapController::class, 'index'])->name('sitemap');
```

## Site name

Configured via `SiteSettingsService::get('branding.site_name', 'Marketplace')`. Verified at:

```bash
$ grep -n "site_name" resources/js/Layouts/StorefrontLayout.tsx | head
```

The layout reads from shared Inertia props (`siteSettings.branding.site_name`) — Phase 11B.3.3 established this pattern.

## Logo, favicon

- Logo: `siteSettings.branding.logo_url` → served from `storage/app/public/branding/`
- Favicon: `siteSettings.branding.favicon_url` OR default `/favicon.ico` in public/

Verify both exist after upload via admin:

```bash
curl -sI https://YOUR_DOMAIN/favicon.ico | head -1
# Expected: 200

curl -sI https://YOUR_DOMAIN/storage/branding/logo.png | head -1
# Expected: 200 (if uploaded)
```

## Meta title / description

Per-page via Inertia's `<Head>` component:

```tsx
<Head title={title} />
```

Product pages have `meta_title` and `meta_description` columns on `products` table:

```bash
$ grep "meta_title\|meta_description" database/migrations/2026_01_03_000003_create_products_table.php | head
```

For pages without a `meta_description`, defaults come from `siteSettings.seo.default_description` — configure via admin.

## Open Graph image

- Per-product: `products.og_image` or falls back to `product.images[0]`
- Site-wide default: `siteSettings.seo.default_og_image`

Verified rendering in layout head via Inertia shared props. The operator uploads a default OG image via admin (recommended size: 1200×630).

## Canonical URL

Inertia auto-generates canonical URLs from `APP_URL` + current path. Verify:

```bash
curl -s https://YOUR_DOMAIN/products/some-slug | grep -i canonical
```

Expected: `<link rel="canonical" href="https://YOUR_DOMAIN/products/some-slug">`.

Any canonical pointing at `localhost`, staging URL, or `http://` is a bug. Usually caused by wrong `APP_URL`.

## Public product URLs

Format: `/products/{slug}` — slugified from `product.name` (or `product.name_translations.en`) via `Str::slug()`. Auto-generated at creation.

Slugs are unique per vendor: `UNIQUE(vendor_id, slug)` in migration.

## Arabic SEO basics

- Arabic content served with `lang="ar" dir="rtl"` on the HTML root
- Meta description supports Arabic (utf8mb4 column)
- URLs stay English (romanized) — search engines prefer this
- `hreflang` alternates: If serving the same product in both en and ar, add:

  ```html
  <link rel="alternate" hreflang="en" href="https://YOUR_DOMAIN/products/some-slug">
  <link rel="alternate" hreflang="ar" href="https://YOUR_DOMAIN/products/some-slug?locale=ar">
  <link rel="alternate" hreflang="x-default" href="https://YOUR_DOMAIN/products/some-slug">
  ```

Verify hreflang is present or absent as intended in the storefront layout's `<Head>`.

## Header / footer / social links

All configurable via admin site-settings groups: `header`, `footer`, `social`.

Runtime check the operator should perform:

- Every footer link resolves to a real URL (no `#` placeholders)
- Social icons point to real profile URLs (not staging URLs)
- Contact email in the footer is a monitored inbox

## No staging URLs remain

Grep the codebase and public build for common staging leaks:

```bash
$ grep -rn "staging\.\|localhost\|127\.0\.0\.1\|test\.example" resources/ config/ 2>/dev/null | grep -v ".env.example" | grep -v "// "
```

Expected: only false positives (e.g. `localhost` in code comments, `127.0.0.1` in `config/database.php` default).

## No broken public links

Runtime crawl the operator should perform post-deploy:

```bash
# Using a link checker (e.g. broken-link-checker, linkchecker):
blc https://YOUR_DOMAIN/ -ro --exclude-external
```

Every internal link should return 200 or a legitimate 301/302. Any 4xx/5xx needs fixing.

## Public storage URLs

Sample check:

```bash
curl -sI https://YOUR_DOMAIN/storage/products/1/main.jpg
# Expected: 200 (if product 1 has this image)

curl -sI https://YOUR_DOMAIN/storage/vendors/1/logo.png
# Expected: 200 (if vendor 1 has a logo)
```

If 404, either:
- `storage:link` wasn't run → run `php artisan storage:link`
- File doesn't exist at that path → check admin upload flow

## Email domain alignment

The `MAIL_FROM_ADDRESS` should be on the same domain as `APP_URL`. Mismatch (`APP_URL=marketplace.kw`, `MAIL_FROM_ADDRESS=noreply@example.com`) triggers spam filters.

Verify:

```bash
$ grep -E "^(APP_URL|MAIL_FROM_ADDRESS)=" .env | awk -F= '{print $2}'
```

Both should be on the same second-level domain.

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| `public/robots.txt` exists | ✅ | `ls public/robots.txt` |
| `SitemapController` exists | ✅ | `ls app/Http/Controllers/Public/SitemapController.php` |
| Product meta_title/meta_description in schema | ✅ | Migration inspection |
| Layout uses Inertia `<Head>` component | ✅ | `grep "<Head" resources/js/Layouts/*.tsx` |
| `SiteSettingsService` provides branding to layout (Phase 11B.3.3) | ✅ | v11B.3.3 approved fix preserved |
| Sitemap route registered | ⏳ | Operator runs `grep sitemap routes/web.php` |
| Live URLs return 200 for meta pages | ⏳ | Operator crawls |
| No staging URLs in production | ⏳ | Operator grep after deploy |
| Favicon uploaded | ⏳ | Operator uploads via admin |
| Default OG image uploaded | ⏳ | Operator uploads via admin |
| MAIL_FROM_ADDRESS aligned with APP_URL | ⏳ | Operator checks `.env` |
