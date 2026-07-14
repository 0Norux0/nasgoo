<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AddressResource\Pages;
use App\Models\Address;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AddressResource extends Resource
{
    protected static ?string $model = Address::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Owner')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                ]),
            Forms\Components\Section::make('Address')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('label')->maxLength(60)->placeholder('Home, Office...'),
                    Forms\Components\Select::make('type')
                        ->options(['shipping' => 'Shipping', 'billing' => 'Billing', 'both' => 'Both'])
                        ->default('shipping')
                        ->required(),
                    Forms\Components\Toggle::make('is_default'),
                    Forms\Components\TextInput::make('country')->length(2)->required()->placeholder('KW'),
                    Forms\Components\TextInput::make('state')->maxLength(80),
                    Forms\Components\TextInput::make('city')->required()->maxLength(80),
                    Forms\Components\TextInput::make('area')->maxLength(80),
                    Forms\Components\TextInput::make('block')->maxLength(20),
                    Forms\Components\TextInput::make('street')->maxLength(120),
                    Forms\Components\TextInput::make('building')->maxLength(60),
                    Forms\Components\TextInput::make('floor')->maxLength(20),
                    Forms\Components\TextInput::make('apartment')->maxLength(20),
                    Forms\Components\TextInput::make('postal_code')->maxLength(20),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(40),
                    Forms\Components\TextInput::make('latitude')->numeric(),
                    Forms\Components\TextInput::make('longitude')->numeric(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('User')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('label')->placeholder('—'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('country')->badge(),
                Tables\Columns\TextColumn::make('city')->searchable(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(['shipping' => 'Shipping', 'billing' => 'Billing', 'both' => 'Both']),
                Tables\Filters\SelectFilter::make('country'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAddresses::route('/'),
            'create' => Pages\CreateAddress::route('/create'),
            'edit'   => Pages\EditAddress::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }
}
