<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierIntegrationResource\Pages;
use App\Models\SupplierIntegration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierIntegrationResource extends Resource
{
    protected static ?string $model = SupplierIntegration::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Supplier Integrations';
    protected static ?int $navigationSort = 61;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('vendor_id')
                ->relationship('vendor', 'business_name')
                ->required()->searchable(),
            Forms\Components\Select::make('supplier_platform_id')
                ->relationship('platform', 'name')
                ->required(),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Select::make('integration_type')
                ->options(['manual'=>'Manual','csv'=>'CSV','api'=>'API','feed'=>'Feed'])
                ->required(),
            Forms\Components\TextInput::make('feed_url')->url()->maxLength(255),
            // Credentials: stored encrypted; never edit raw from admin UI in production.
            // The Filament form here only shows a read-only mask.
            Forms\Components\Placeholder::make('credentials_mask')
                ->label('Credentials (masked)')
                ->content(fn (?SupplierIntegration $record) => $record
                    ? collect($record->maskedCredentials())->map(fn ($v, $k) => "{$k}: {$v}")->join(' · ')
                    : '—'),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\DateTimePicker::make('last_synced_at')->disabled(),
            Forms\Components\TextInput::make('last_sync_status')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('platform.name')->label('Platform')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('integration_type')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('last_synced_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('last_sync_status')->badge()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')->relationship('platform', 'name'),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // v6.4 lesson: eager-load every relation accessed in table/view/edit closures.
        return parent::getEloquentQuery()->with(['vendor', 'platform']);
    }

    public static function canAccess(): bool { return auth()->user()?->can('supplier_integrations.view') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->can('supplier_integrations.create') ?? false; }
    public static function canEdit($record): bool { return auth()->user()?->can('supplier_integrations.update') ?? false; }
    public static function canDelete($record): bool { return auth()->user()?->can('supplier_integrations.delete') ?? false; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSupplierIntegrations::route('/'),
            'create' => Pages\CreateSupplierIntegration::route('/create'),
            'view'   => Pages\ViewSupplierIntegration::route('/{record}'),
            'edit'   => Pages\EditSupplierIntegration::route('/{record}/edit'),
        ];
    }
}
