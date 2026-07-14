<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingMethodResource\Pages;
use App\Models\ShippingMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 6;
    protected static ?string $modelLabel = 'Shipping Method';
    protected static ?string $pluralModelLabel = 'Shipping Methods';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Method')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('shipping_zone_id')
                        ->relationship('zone', 'name')
                        ->placeholder('Global (any zone)'),
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('slug')->maxLength(140)
                        ->helperText('Auto-generated from name if blank.'),
                    Forms\Components\Select::make('type')->required()
                        ->options([
                            ShippingMethod::TYPE_FLAT_RATE => 'Flat rate',
                            ShippingMethod::TYPE_FREE      => 'Free shipping',
                            ShippingMethod::TYPE_PICKUP    => 'Pickup',
                        ])->live(),
                    Forms\Components\TextInput::make('fee_minor')->numeric()->default(0)
                        ->visible(fn (Forms\Get $get) => $get('type') === ShippingMethod::TYPE_FLAT_RATE)
                        ->helperText('Stored in minor units (e.g. fils for KWD).'),
                    Forms\Components\TextInput::make('currency')->default('KWD')->maxLength(3),
                    Forms\Components\TextInput::make('min_subtotal_minor')->numeric()
                        ->visible(fn (Forms\Get $get) => $get('type') === ShippingMethod::TYPE_FREE)
                        ->helperText('Optional: cart subtotal must reach this for free shipping to apply.'),
                    Forms\Components\TextInput::make('max_weight_grams')->numeric()
                        ->helperText('Optional weight cap (in grams).'),
                    Forms\Components\TextInput::make('eta_label')->maxLength(120)
                        ->placeholder('e.g. 2-3 business days'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\TextInput::make('position')->numeric()->default(0),
                    Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zone.name')->label('Zone')->placeholder('Global'),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ShippingMethod::TYPE_FREE      => 'success',
                        ShippingMethod::TYPE_FLAT_RATE => 'info',
                        ShippingMethod::TYPE_PICKUP    => 'gray',
                        default                        => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fee_minor')->label('Fee')
                    ->formatStateUsing(fn ($state, $record) => $state ? number_format(((int) $state) / 100, 3) . ' ' . $record->currency : '—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('position')->sortable(),
            ])
            ->defaultSort('position')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListShippingMethods::route('/'),
            'create' => Pages\CreateShippingMethod::route('/create'),
            'edit'   => Pages\EditShippingMethod::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin_staff']) ?? false;
    }
}
