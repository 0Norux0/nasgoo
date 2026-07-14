<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ in_array(app()->getLocale(), ['ar', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Marketplace') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    {{-- Arabic-friendly font --}}
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700&display=swap" rel="stylesheet">

    {{--
        v3.1 — Asset loading
        - The @routes directive (Ziggy) was removed; we don't use route() in React.
        - @vite preloads only the two real entry points. Pages are code-split via
          import.meta.glob inside resources/js/app.tsx, so the manifest contains
          one chunk per page automatically; we do NOT include a dynamic page path
          here (it was a fragile preload optimisation that broke quietly when the
          manifest didn't index it under the exact key).
    --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead

    {{-- Phase 11B.3 v11B.3.3 §12 — server-side CSS custom property injection
         from siteSettings.appearance. Admin sets `color_primary` etc. in
         /admin/site-settings; those values become CSS variables here so
         they take effect on the live site without a rebuild.

         Values are hex-color-validated admin-side (regex ^#[0-9a-fA-F]{3,8}$)
         but we still e() them to prevent injection if the DB was compromised.
         Defensive try/catch means a failing settings service can't 500 the
         page — the fallback is no custom vars, which just means Tailwind
         base colors are used. --}}
    @php
        try {
            $appearance = app(\App\Services\Settings\SiteSettingsService::class)->group('appearance');
        } catch (\Throwable $e) {
            \Log::warning('v11B.3.3 CSS var injection failed (defensive catch)', ['err' => $e->getMessage()]);
            $appearance = [];
        }
    @endphp
    @if(!empty($appearance))
        <style id="v11b33-appearance-vars" data-testid="appearance-css-vars">
            :root {
                @foreach($appearance as $key => $value)
                    @if(is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value))
                        --{{ str_replace('_', '-', e($key)) }}: {{ e($value) }};
                    @endif
                @endforeach
            }
        </style>
        @if(!empty($appearance['browser_theme_color']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $appearance['browser_theme_color']))
            <meta name="theme-color" content="{{ e($appearance['browser_theme_color']) }}">
        @endif
    @endif
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-900">
    @inertia
</body>
</html>
