<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Class Namespace + View Path (Livewire defaults — Filament uses Livewire
    | under the hood, so we keep these intact)
    |--------------------------------------------------------------------------
    */

    'class_namespace' => 'App\\Livewire',
    'view_path' => resource_path('views/livewire'),
    'layout' => 'components.layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Lazy Loading Placeholder (Livewire default)
    |--------------------------------------------------------------------------
    */

    'lazy_placeholder' => null,

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    | v5.6 — explicitly point Livewire's temporary uploads at the `local`
    | disk (private). Filament/Livewire stages an upload here before moving it
    | to the final media disk (`public`) on form submit. If this is left to
    | runtime defaults the temp file can land somewhere with restrictive
    | permissions → 403 on the next stage.
    |
    | Rules below mirror our ProductResource validation: JPG / PNG / WEBP up
    | to 5 MB. The upload directory is `livewire-tmp/` under the disk root.
    */

    'temporary_file_upload' => [
        'disk' => 'local',           // stays inside storage/app/private/livewire-tmp
        'rules' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        'directory' => 'livewire-tmp',
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a', 'jpg', 'jpeg', 'mpga',
            'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Render On Redirect
    |--------------------------------------------------------------------------
    */

    'render_on_redirect' => false,

    /*
    |--------------------------------------------------------------------------
    | Eloquent Model Binding
    |--------------------------------------------------------------------------
    */

    'legacy_model_binding' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto-inject Frontend Assets
    |--------------------------------------------------------------------------
    | Filament includes Livewire assets itself, but this is harmless.
    */

    'inject_assets' => true,

    /*
    |--------------------------------------------------------------------------
    | Navigate (SPA Mode)
    |--------------------------------------------------------------------------
    */

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2299dd',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Morph Markers
    |--------------------------------------------------------------------------
    */

    'inject_morph_markers' => true,

    /*
    |--------------------------------------------------------------------------
    | Pagination Theme
    |--------------------------------------------------------------------------
    */

    'pagination_theme' => 'tailwind',
];
