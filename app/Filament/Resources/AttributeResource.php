<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AttributeResource\Pages;
use App\Models\Attribute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Attribute')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('slug')->helperText('Auto from name if blank.'),
                    Forms\Components\Select::make('type')
                        ->options(['select' => 'Select', 'text' => 'Text', 'number' => 'Number', 'boolean' => 'Boolean'])
                        ->default('select')->required(),
                    Forms\Components\TextInput::make('position')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_filterable')->default(true),
                    Forms\Components\Toggle::make('is_variation')->helperText('Use this attribute to build product variants (e.g. Color, Size).'),
                ]),

            Forms\Components\Section::make('Translations')
                ->collapsed()
                ->schema([
                    Forms\Components\KeyValue::make('name_translations')
                        ->keyLabel('Locale')->valueLabel('Translated name'),
                ]),

            Forms\Components\Section::make('Values')
                ->schema([
                    Forms\Components\Repeater::make('values')
                        ->relationship()
                        ->columns(4)
                        ->schema([
                            Forms\Components\TextInput::make('value')->required(),
                            Forms\Components\TextInput::make('slug')->helperText('Auto from value if blank.'),
                            Forms\Components\TextInput::make('color_hex')->placeholder('#FF0000'),
                            Forms\Components\TextInput::make('position')->numeric()->default(0),
                        ])
                        ->reorderableWithButtons()
                        ->collapsible(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('values_count')->counts('values')->label('Values'),
                Tables\Columns\IconColumn::make('is_filterable')->boolean(),
                Tables\Columns\IconColumn::make('is_variation')->boolean()->label('Variation'),
                Tables\Columns\TextColumn::make('position')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAttributes::route('/'),
            'create' => Pages\CreateAttribute::route('/create'),
            'edit'   => Pages\EditAttribute::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool { return auth()->user()?->can('attributes.manage') || auth()->user()?->can('products.view') || false; }
    public static function canCreate(): bool { return auth()->user()?->can('attributes.manage') ?? false; }
    public static function canEdit($r): bool { return auth()->user()?->can('attributes.manage') ?? false; }
    public static function canDelete($r): bool { return auth()->user()?->can('attributes.manage') ?? false; }
}
