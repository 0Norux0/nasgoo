# Server Deploy Fixes Checklist

Use this when the Laravel app is uploaded to the server and starts failing because
runtime folders, storage links, or product image paths are missing.

## 1. Fix Missing Laravel Runtime Folders

Common errors:

```text
Please provide a valid cache path.
file_put_contents(.../storage/framework/sessions/...): Failed to open stream: No such file or directory
```

Run this on the server from the project root:

```bash
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

chmod -R 775 storage bootstrap/cache
php artisan optimize:clear
php artisan package:discover --ansi
```

## 2. Keep Laravel Able To Self-Heal These Folders

Add this to `app/Providers/AppServiceProvider.php`.

Import:

```php
use Illuminate\Support\Facades\File;
```

Call this at the top of `boot()`:

```php
$this->ensureRuntimeDirectoriesExist();
```

Add this method inside the class:

```php
private function ensureRuntimeDirectoriesExist(): void
{
    foreach ([
        storage_path('app/public'),
        storage_path('framework/cache'),
        storage_path('framework/cache/data'),
        storage_path('framework/sessions'),
        storage_path('framework/testing'),
        storage_path('framework/views'),
        storage_path('logs'),
        base_path('bootstrap/cache'),
    ] as $path) {
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }
}
```

## 3. Restore Missing `config/view.php`

If Composer fails during:

```text
@php artisan package:discover --ansi
InvalidArgumentException: Please provide a valid cache path.
```

make sure `config/view.php` exists:

```php
<?php

declare(strict_types=1);

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views')) ?: storage_path('framework/views'),
    ),
];
```

Then run:

```bash
php artisan optimize:clear
php artisan package:discover --ansi
```

## 4. Fix Product Images To Use Relative Paths

The database should store product image paths like this:

```text
products/demo/stainless-steel-water-bottle.svg
products/1/25/photo.webp
```

Do not store server URLs or `/storage/...` paths in `product_images.path`.

Bad:

```text
https://nasgo.co/storage/products/demo/stainless-steel-water-bottle.svg
/storage/products/demo/stainless-steel-water-bottle.svg
storage/app/public/products/demo/stainless-steel-water-bottle.svg
```

Good:

```text
products/demo/stainless-steel-water-bottle.svg
```

Laravel serves it as:

```text
/storage/products/demo/stainless-steel-water-bottle.svg
```

The universal fix is:

- Normalize public image paths before saving them.
- Keep external CDN URLs only if they are truly external.
- Generate `/storage/...` only when rendering the image URL.

Files involved:

```text
app/Support/MarketplaceMedia.php
app/Models/ProductImage.php
app/Http/Controllers/Vendor/VendorProductController.php
database/seeders/DemoSeeder.php
```

## 5. Restore Missing Demo Product Images

If this URL breaks:

```text
https://nasgo.co/storage/products/demo/stainless-steel-water-bottle.svg
```

Laravel expects the file here:

```text
storage/app/public/products/demo/stainless-steel-water-bottle.svg
```

Create the directory:

```bash
mkdir -p storage/app/public/products/demo
```

Then restore the demo files:

```text
storage/app/public/products/demo/wireless-bluetooth-headphones.svg
storage/app/public/products/demo/cotton-t-shirt-classic-fit.svg
storage/app/public/products/demo/stainless-steel-water-bottle.svg
storage/app/public/products/demo/handwoven-beach-towel.svg
```

The seeder should also recreate demo SVG files if the DB rows exist but the
actual storage files are missing.

## 6. Make Sure Demo SVGs Are Not Ignored By Git

Laravel's default storage ignore rules can hide these files from Git.

If the demo SVGs exist locally but do not appear on hosting, check:

```bash
git check-ignore -v storage/app/public/products/demo/stainless-steel-water-bottle.svg
git status --short --untracked-files=all storage/app/public/products/demo
```

If Git ignores the SVGs, add narrow unignore rules. Do not unignore the whole
`storage` folder.

`storage/.gitignore`:

```gitignore
*
!app/
!public/
!public/.gitignore
!.gitignore
```

`storage/app/.gitignore`:

```gitignore
*
!public/
!.gitignore
```

`storage/app/public/.gitignore`:

```gitignore
*
!.gitignore
!products/
!products/demo/
!products/demo/*.svg
```

`storage/app/public/products/.gitignore`:

```gitignore
*
!.gitignore
!demo/
```

`storage/app/public/products/demo/.gitignore`:

```gitignore
*
!.gitignore
!*.svg
```

After that, Git should show:

```text
storage/app/public/products/demo/wireless-bluetooth-headphones.svg
storage/app/public/products/demo/cotton-t-shirt-classic-fit.svg
storage/app/public/products/demo/stainless-steel-water-bottle.svg
storage/app/public/products/demo/handwoven-beach-towel.svg
```

Then commit/upload those SVG files with the code.

## 7. Recreate The Public Storage Link

Run this once on the server:

```bash
php artisan storage:link
```

Expected mapping:

```text
public/storage -> storage/app/public
```

If the link already exists, this may say it already exists. That is fine.

## 8. Quick Server Recovery Command

Use this when you just want the direct fix:

```bash
mkdir -p storage/app/public/products/demo
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan optimize:clear
php artisan package:discover --ansi
```

## 9. What Does Not Change

These fixes do not require database changes.

No migrations are needed.
No tables need to be edited.
No seeders need to be rerun unless demo products/images are missing from the DB.
