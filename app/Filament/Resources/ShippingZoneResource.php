<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingZoneResource\Pages;
use App\Models\ShippingZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 5;
    protected static ?string $modelLabel = 'Shipping Zone';
    protected static ?string $pluralModelLabel = 'Shipping Zones';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Zone')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('slug')->maxLength(140)
                        ->helperText('Auto-generated from name if blank.'),
                    Forms\Components\TagsInput::make('countries')->required()
                        ->placeholder('KW, AE, SA...')
                        ->helperText('ISO 3166-1 alpha-2 codes (uppercase). Use "*" for any country.'),
                    Forms\Components\TagsInput::make('regions')
                        ->placeholder('Kuwait City, Salmiya...')
                        ->helperText('Optional: leave blank to cover entire country. If set, address region/city must match.'),
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
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('countries')->formatStateUsing(
                    fn ($state) => is_array($state) ? implode(', ', $state) : (string) $state
                ),
                Tables\Columns\TextColumn::make('regions')->formatStateUsing(
                    fn ($state) => is_array($state) && ! empty($state) ? implode(', ', $state) : '— country-wide'
                )->toggleable(),
                Tables\Columns\TextColumn::make('methods_count')->counts('methods')->label('Methods'),
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
            'index'  => Pages\ListShippingZones::route('/'),
            'create' => Pages\CreateShippingZone::route('/create'),
            'edit'   => Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin_staff']) ?? false;
    }
}
