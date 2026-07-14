<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\RecentAuditLogs;
use App\Filament\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getTitle(): string
    {
        return 'Marketplace Dashboard';
    }

    public function getHeading(): string
    {
        return 'Phase 2 — Vendor System Active';
    }

    public function getSubheading(): ?string
    {
        return 'Vendors · Packages · Subscriptions · Commission Rules · Users · Roles · Settings · Audit';
    }

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            RecentAuditLogs::class,
        ];
    }
}
