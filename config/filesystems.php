<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    | App internals (private). Product media is stored explicitly on the
    | 'public' disk (see App\Http\Controllers\Vendor\VendorProductController
    | ::storeImages) so it's reachable at /storage/... after `storage:link`.
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
        ],

        // Public media — product images, vendor logos, etc. Exposed at
        // /storage/... via `php artisan storage:link`.
        //
        // v5.6 — explicit `permissions` block. Without this, file mode depends
        // on the process umask. In Docker containers (or any environment where
        // the PHP user differs from the web-server user) the default umask can
        // produce files like 0640 — `php artisan serve` then returns 403 when
        // trying to read them. Pinning to 0644/0755 makes uploaded files
        // unconditionally world-readable.
        'public' => [
            'driver'      => 'local',
            'root'        => storage_path('app/public'),
            'url'         => env('APP_URL', 'http://localhost') . '/storage',
            'visibility'  => 'public',
            'throw'       => false,
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0644],
                'dir'  => ['public' => 0755, 'private' => 0755],
            ],
        ],

        // Cloudflare R2 / MinIO (S3-compatible). Used in production when
        // MEDIA_DISK=s3. Falls back to the 'public' local disk otherwise.
        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw'                   => false,
            'visibility'              => 'public',
        ],

        // Phase 10 v10.6 — dedicated PRIVATE disk for vendor application
        // documents (license, ID). Resolves the runtime crash
        //
        //     InvalidArgumentException: Disk [vendors] does not have a
        //     configured driver.
        //
        // v10.1's VendorFileController.show() reads via
        // `Storage::disk(config('marketplace.vendor_private_disk', 'vendors'))`,
        // but `config/filesystems.php` had no 'vendors' entry, so the call
        // crashed when an admin opened a pending vendor application.
        //
        // Driver: local (private). Root is storage/app/private — same as
        // the default 'local' disk — so the disk name 'vendors' is just a
        // semantic alias. Uploads via VendorRegistrationController write
        // to the default disk with paths like `vendors/{id}/{filename}`
        // (i.e. the actual files live at
        // storage/app/private/vendors/{id}/{filename}). The path stored
        // in the DB is `vendors/{id}/{filename}`. To make
        // Storage::disk('vendors')->exists($path) find the file, the disk
        // root must match where the writes land — storage/app/private,
        // NOT storage/app/private/vendors (the latter would cause path
        // double-nesting and 404s).
        'vendors' => [
            'driver'     => 'local',
            'root'       => storage_path('app/private'),
            'visibility' => 'private',
            'throw'      => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    | `php artisan storage:link` creates these. Maps public/storage →
    | storage/app/public so uploaded media is served by the web server.
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
