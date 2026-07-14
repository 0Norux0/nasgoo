<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Phase 6 v7.2 — explicit console command discovery. Laravel 11's
    // default already scans app/Console/Commands; being explicit here
    // documents intent and protects against any future change to that
    // default. Picks up `marketplace:setup-demo` and any future commands.
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Inertia middleware shares props with every request; SetLocale
        // resolves the active UI language before HandleInertiaRequests runs
        // so the shared translations array reflects the chosen locale.
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Sanctum stateful API for the SPA + future mobile token auth
        $middleware->statefulApi();

        // Role/permission middleware aliases (Spatie)
        $middleware->alias([
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'=> \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'vendor'            => \App\Http\Middleware\EnsureVendor::class,
            'license'           => \App\Http\Middleware\EnsureValidLicense::class,
        ]);

        // Phase 12.3 — license gate runs late in the web pipeline. When
        // enforcement_enabled=false in config, it's a no-op passthrough.
        // Exempt routes (activation, login, health, storefront) are
        // handled inside the middleware itself.
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureValidLicense::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Phase-1 will register Inertia-aware error responses here
    })
    ->create();
