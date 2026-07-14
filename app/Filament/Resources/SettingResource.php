<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Setting')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('group')
                        ->options([
                            'general'     => 'General',
                            'marketplace' => 'Marketplace',
                            'currency'    => 'Currency',
                            'payment'     => 'Payment',
                            'shipping'    => 'Shipping',
                            'commission'  => 'Commission',
                            'email'       => 'Email',
                            'seo'         => 'SEO',
                            'social'      => 'Social',
                            'security'    => 'Security',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('key')->required()->maxLength(120),
                    Forms\Components\Select::make('type')
                        ->options([
                            'string'    => 'String',
                            'integer'   => 'Integer',
                            'boolean'   => 'Boolean',
                            'array'     => 'Array',
                            'json'      => 'JSON',
                            'encrypted' => 'Encrypted',
                        ])
                        ->default('string')
                        ->required(),
                    Forms\Components\Toggle::make('is_public')->helperText('Exposed to frontend via shared props.'),
                    Forms\Components\Toggle::make('is_encrypted'),
                ]),

            Forms\Components\Section::make('Value')
                ->schema([
                    Forms\Components\Textarea::make('value')
                        ->required()
                        ->rows(6)
                        ->helperText('For arrays/JSON, enter valid JSON. The system stores this in a typed envelope.')
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state)
                        ->dehydrateStateUsing(function ($state) {
                            // Wrap raw input
                            $decoded = json_decode((string) $state, true);
                            return Setting::wrap($decoded !== null ? $decoded : $state);
                        }),
                    Forms\Components\Textarea::make('description')->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('value')
                    ->limit(40)
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state),
                Tables\Columns\IconColumn::make('is_public')->boolean(),
                Tables\Columns\IconColumn::make('is_encrypted')->boolean()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->options([
                        'general' => 'general', 'marketplace' => 'marketplace', 'currency' => 'currency',
                        'payment' => 'payment', 'shipping' => 'shipping', 'commission' => 'commission',
                        'email' => 'email', 'seo' => 'seo', 'social' => 'social', 'security' => 'security',
                    ]),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('group');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit'   => Pages\EditSetting::route('/{record}/edit'),
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
