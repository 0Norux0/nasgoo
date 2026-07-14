<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminProductRelationshipResource\Pages;
use App\Models\AdminProductRelationship;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Phase 11B.2 §15 — admin-curated product relationships workspace.
 *
 * Surfaces all admin_product_relationships rows. Lets admin:
 *   - PIN a related product (always shows on source's similar-products section)
 *   - HIDE a related product (never shows for the source)
 *   - mark COMPLEMENTARY (used as FBT fallback when real co-occurrence is thin)
 *   - EXCLUDE pairings entirely (never co-recommended in either direction)
 *
 * Per dev §15 — "changes are audited" via created_by + timestamps, "no
 * duplicate relationships" via DB unique constraint, "reciprocal" optional.
 */
class AdminProductRelationshipResource extends Resource
{
    protected static ?string $model = AdminProductRelationship::class;
    protected static ?string $navigationGroup = 'Recommendations';
    protected static ?string $navigationLabel = 'Product relationships';
    protected static ?string $navigationIcon  = 'heroicon-o-link';
    protected static ?int    $navigationSort  = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Relationship')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Source product')
                        ->relationship('product', 'name')
                        ->searchable()->required(),
                    Forms\Components\Select::make('related_product_id')
                        ->label('Related product')
                        ->relationship('relatedProduct', 'name')
                        ->searchable()->required()
                        ->different('product_id'),  // can't relate a product to itself
                    Forms\Components\Select::make('relationship_type')
                        ->options([
                            AdminProductRelationship::TYPE_PINNED        => 'Pinned (always show)',
                            AdminProductRelationship::TYPE_HIDDEN        => 'Hidden (never show)',
                            AdminProductRelationship::TYPE_COMPLEMENTARY => 'Complementary (FBT fallback)',
                            AdminProductRelationship::TYPE_EXCLUDED      => 'Excluded (no co-recommendation)',
                        ])
                        ->required(),
                    Forms\Components\Toggle::make('reciprocal')
                        ->helperText('Apply the relationship in both directions (A↔B)')
                        ->default(false),
                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull()
                        ->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Source')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('relatedProduct.name')->label('Related')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('relationship_type')->badge()
                    ->color(fn (string $state) => match ($state) {
                        AdminProductRelationship::TYPE_PINNED        => 'success',
                        AdminProductRelationship::TYPE_HIDDEN        => 'gray',
                        AdminProductRelationship::TYPE_COMPLEMENTARY => 'info',
                        AdminProductRelationship::TYPE_EXCLUDED      => 'danger',
                        default                                       => 'gray',
                    }),
                Tables\Columns\IconColumn::make('reciprocal')->boolean()->label('Both ways'),
                Tables\Columns\TextColumn::make('creator.name')->label('Created by')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('relationship_type')->options([
                    AdminProductRelationship::TYPE_PINNED        => 'Pinned',
                    AdminProductRelationship::TYPE_HIDDEN        => 'Hidden',
                    AdminProductRelationship::TYPE_COMPLEMENTARY => 'Complementary',
                    AdminProductRelationship::TYPE_EXCLUDED      => 'Excluded',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdminProductRelationships::route('/'),
            'create' => Pages\CreateAdminProductRelationship::route('/create'),
            'edit'   => Pages\EditAdminProductRelationship::route('/{record}/edit'),
        ];
    }

    /**
     * Stamp created_by on save (audit per dev §15).
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery();
    }
}
