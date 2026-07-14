<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_type')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : null)
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('ip_address')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options(fn () => AuditLog::query()
                        ->distinct()
                        ->pluck('action', 'action')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Actor')
                    ->relationship('user', 'name'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Action')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    Infolists\Components\TextEntry::make('action')->badge(),
                    Infolists\Components\TextEntry::make('user.name')->label('Actor')->placeholder('System'),
                    Infolists\Components\TextEntry::make('ip_address'),
                    Infolists\Components\TextEntry::make('user_agent')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('No notes.'),
                ]),
            Infolists\Components\Section::make('Subject')
                ->columns(2)
                ->visible(fn ($record) => $record->model_type !== null)
                ->schema([
                    Infolists\Components\TextEntry::make('model_type'),
                    Infolists\Components\TextEntry::make('model_id'),
                ]),
            Infolists\Components\Section::make('Before')
                ->visible(fn ($record) => filled($record->before))
                ->schema([
                    Infolists\Components\TextEntry::make('before')
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : (string) $state),
                ]),
            Infolists\Components\Section::make('After')
                ->visible(fn ($record) => filled($record->after))
                ->schema([
                    Infolists\Components\TextEntry::make('after')
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : (string) $state),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view'  => Pages\ViewAuditLog::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('audit_logs.view') ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
