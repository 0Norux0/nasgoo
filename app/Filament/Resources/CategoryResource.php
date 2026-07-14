<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent category')
                        ->relationship('parent', 'name')
                        ->searchable()
                        ->helperText('Leave empty for a top-level category.'),
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('slug')->helperText('Auto-generated if blank.'),
                    Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Translations')
                ->collapsed()
                ->schema([
                    Forms\Components\KeyValue::make('name_translations')
                        ->label('Name in other locales')
                        ->keyLabel('Locale (ar, ur, …)')
                        ->valueLabel('Translated name')
                        ->reorderable(false),
                ]),

            Forms\Components\Section::make('Display')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('icon_path'),
                    Forms\Components\TextInput::make('image_path'),
                    Forms\Components\TextInput::make('position')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('parent.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('depth')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('products_count')->label('Products')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('position')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('parent_id')
                    ->relationship('parent', 'name')
                    ->label('Parent'),
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
            'index'  => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit'   => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('categories.manage')
            || auth()->user()?->can('products.view')
            || false;
    }
    public static function canCreate(): bool { return auth()->user()?->can('categories.manage') ?? false; }
    public static function canEdit($r): bool { return auth()->user()?->can('categories.manage') ?? false; }
    public static function canDelete($r): bool { return auth()->user()?->can('categories.manage') ?? false; }
}
