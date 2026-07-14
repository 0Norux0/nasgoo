<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentAuditLogs extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent audit log entries';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuditLog::query()->latest('created_at')->limit(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d H:i')->label('When'),
                Tables\Columns\TextColumn::make('user.name')->placeholder('System')->label('Actor'),
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('model_type')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—')
                    ->label('Subject')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('ip_address'),
            ])
            ->paginated(false);
    }
}
