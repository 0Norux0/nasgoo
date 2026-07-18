<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Widgets\RecentAuditLogs;
use App\Filament\Widgets\StatsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->brandName('Marketplace Admin')
            ->colors([
                'primary' => Color::Indigo,
                'gray'    => Color::Slate,
            ])
            ->navigationGroups([
                'Marketplace',
                'Catalog',
                'Operations',
                'Reports',
                'Access Control',
                'Configuration',
            ])
            // Phase 10 v10.1 — add a navigation item linking to the
            // dedicated Inertia-rendered Reports page at /admin/reports.
            // Without this the page existed but was unfindable from
            // the Filament sidebar (the developer's confirmed bug).
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Site design')
                    ->url('/admin/site-settings')
                    ->icon('heroicon-o-photo')
                    ->group('Configuration')
                    ->sort(0)
                    ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
                    ->openUrlInNewTab(false),
                \Filament\Navigation\NavigationItem::make('Reports Dashboard')
                    ->url('/admin/reports')
                    ->icon('heroicon-o-chart-bar')
                    ->group('Reports')
                    ->sort(1)
                    // Phase 10 v10.9 — visibility uses the SAME method as the
                    // route Gate (User::canManageAdminReports). Pre-v10.9 the
                    // menu was role-based and the Gate was permission-based;
                    // any drift (stale Spatie cache, missing permission row
                    // on a pre-Phase-10 DB) caused "menu visible, route 403".
                    // The shared method eliminates the mismatch by construction.
                    ->visible(fn (): bool => auth()->user()?->canManageAdminReports() ?? false)
                    ->openUrlInNewTab(false),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                StatsOverview::class,
                RecentAuditLogs::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
