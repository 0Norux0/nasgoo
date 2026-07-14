<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCustomizationFieldResource\Pages;
use App\Models\ProductCustomizationField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Phase 7 — admin oversight of customization fields across all products.
 * Vendors create+manage their own fields via the vendor side; admins use
 * this resource for moderation and disabling unsafe customizations.
 */
class ProductCustomizationFieldResource extends Resource
{
    protected static ?string $model = ProductCustomizationField::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Customization Fields';
    protected static ?int $navigationSort = 71;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('product.name')->label('Product')->disabled(),
            Forms\Components\TextInput::make('key')->disabled(),
            Forms\Components\TextInput::make('label'),
            Forms\Components\TextInput::make('type')->disabled(),
            Forms\Components\Toggle::make('required')->disabled(),
            Forms\Components\Toggle::make('is_active'),
            Forms\Components\TextInput::make('extra_fee_minor')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('product.vendor.business_name')->label('Vendor')->limit(20)->toggleable(),
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('key')->fontFamily('mono')->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\IconColumn::make('required')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('extra_fee_minor')
                    ->label('Extra fee')
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state / 100, 2) : '—'),
                Tables\Columns\TextColumn::make('sort_order')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(array_combine(
                    ProductCustomizationField::ALL_TYPES,
                    ProductCustomizationField::ALL_TYPES
                )),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('required'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn () => auth()->user()?->can('customization_fields.manage')),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['product:id,name,vendor_id', 'product.vendor:id,business_name']);
    }

    public static function canAccess(): bool { return auth()->user()?->can('customization_fields.view') ?? false; }
    public static function canCreate(): bool { return false; }     // created via vendor side
    public static function canEdit($record): bool { return auth()->user()?->can('customization_fields.manage') ?? false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCustomizationFields::route('/'),
            'edit'  => Pages\EditProductCustomizationField::route('/{record}/edit'),
        ];
    }
}
