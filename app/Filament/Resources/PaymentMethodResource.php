<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Method')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('slug')->required()
                        ->helperText('Stable identifier referenced by orders. Do not rename after live use.'),
                    Forms\Components\Select::make('provider')->required()
                        ->options(['cod' => 'Cash on Delivery', 'manual_transfer' => 'Manual Bank Transfer', 'online_mock' => 'Mock Online (Demo)'])
                        ->helperText('Maps to PaymentProvider implementation.'),
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('position')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Toggle::make('available_at_checkout')->default(true)
                        ->helperText('Hide from customers (admin-set-only) by toggling off.'),
                ]),
            Forms\Components\Section::make('Description & Translations')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('description')->rows(2),
                    Forms\Components\KeyValue::make('name_translations')->keyLabel('Locale')->valueLabel('Translated name'),
                    Forms\Components\KeyValue::make('description_translations')->keyLabel('Locale')->valueLabel('Translated description'),
                ]),
            Forms\Components\Section::make('Provider config')
                ->collapsed()
                ->schema([
                    Forms\Components\KeyValue::make('config')
                        ->helperText('Provider-specific tunables (e.g. force_outcome=success for online_mock).'),
                    Forms\Components\TagsInput::make('supported_currencies')
                        ->helperText('Leave empty to allow all currencies.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('position')->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('slug')->badge(),
            Tables\Columns\TextColumn::make('provider')->badge(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\IconColumn::make('available_at_checkout')->boolean()->label('At checkout'),
        ])
        ->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit'   => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool { return auth()->user()?->can('payment_methods.manage') ?? false; }
}
