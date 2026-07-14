<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierPlatformResource\Pages;
use App\Models\SupplierPlatform;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierPlatformResource extends Resource
{
    protected static ?string $model = SupplierPlatform::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Supplier Platforms';
    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\TextInput::make('slug')->required()->maxLength(120)
                ->helperText('lowercase identifier, e.g. aliexpress'),
            Forms\Components\TextInput::make('website_url')->url()->maxLength(255),
            Forms\Components\Select::make('integration_type')
                ->options([
                    'manual' => 'Manual entry only',
                    'csv'    => 'CSV import',
                    'api'    => 'API-ready',
                    'feed'   => 'Affiliate/feed-ready',
                ])
                ->required()->default('manual'),
            Forms\Components\TextInput::make('default_currency')->default('USD')->maxLength(3),
            Forms\Components\TextInput::make('default_delivery_days')->numeric()->minValue(0)->maxValue(365),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\TextInput::make('display_order')->numeric()->default(0),
            Forms\Components\Textarea::make('notes')->rows(3)->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('display_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('slug')->toggleable(),
                Tables\Columns\TextColumn::make('integration_type')->badge(),
                Tables\Columns\TextColumn::make('default_currency'),
                Tables\Columns\TextColumn::make('default_delivery_days')->label('ETA (days)'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('display_order')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('integration_type')
                    ->options(['manual'=>'Manual','csv'=>'CSV','api'=>'API','feed'=>'Feed']),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('supplier_platforms.view') ?? false;
    }

    public static function canCreate(): bool { return auth()->user()?->can('supplier_platforms.manage') ?? false; }
    public static function canEdit($record): bool { return auth()->user()?->can('supplier_platforms.manage') ?? false; }
    public static function canDelete($record): bool { return auth()->user()?->can('supplier_platforms.manage') ?? false; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSupplierPlatforms::route('/'),
            'create' => Pages\CreateSupplierPlatform::route('/create'),
            'edit'   => Pages\EditSupplierPlatform::route('/{record}/edit'),
        ];
    }
}
