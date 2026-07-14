<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Currency')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->length(3)
                        ->unique(ignoreRecord: true)
                        ->helperText('ISO 4217 3-letter code, e.g. KWD'),
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('symbol')->required()->maxLength(10),
                    Forms\Components\TextInput::make('decimal_places')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(4)
                        ->default(2)
                        ->required()
                        ->helperText('KWD uses 3, USD/AED/PKR use 2.'),
                    Forms\Components\Toggle::make('is_default')
                        ->helperText('Only one currency can be the default.'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('symbol'),
                Tables\Columns\TextColumn::make('decimal_places')->label('Decimals'),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit'   => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }
}
