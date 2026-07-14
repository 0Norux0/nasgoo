<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductTranslationResource\Pages;
use App\Models\ProductTranslation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Phase 11B.1 v11B.1.2 §9 — admin translation workspace.
 *
 * Surfaces every product_translations row for moderation. Supports the
 * full workflow (missing → pending → machine_draft → human_reviewed →
 * approved | rejected → stale) with bulk approve/reject and explicit
 * status filters. The source English value is shown alongside the
 * target translation so reviewers can compare without leaving the form.
 *
 * Limits:
 *   - Categories / services / pages get separate Filament resources in
 *     future versions; this resource covers products only.
 *   - Mass import/export is handled by Artisan commands, not this UI.
 */
class ProductTranslationResource extends Resource
{
    protected static ?string $model = ProductTranslation::class;
    protected static ?string $navigationGroup = 'Localization';
    protected static ?string $navigationLabel = 'Product translations';
    protected static ?string $navigationIcon  = 'heroicon-o-language';
    protected static ?int    $navigationSort  = 90;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Source (English)')
                ->columns(1)
                ->schema([
                    Forms\Components\Placeholder::make('source_value')
                        ->label('English source value')
                        ->content(function (?ProductTranslation $record) {
                            if (! $record || ! $record->product) {
                                return '—';
                            }
                            return match ($record->field) {
                                'name'              => $record->product->name,
                                'short_description' => $record->product->short_description,
                                'description'       => $record->product->description,
                                default             => '—',
                            };
                        }),
                    Forms\Components\Placeholder::make('source_checksum')
                        ->label('Source checksum (when translation approved)')
                        ->content(fn (?ProductTranslation $r) => $r?->source_checksum ?? '— never approved —'),
                ]),

            Forms\Components\Section::make('Translation')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->relationship('product', 'name')->searchable()->required()->disabled(),
                    Forms\Components\Select::make('locale')
                        ->options(['ar' => 'العربية (Arabic)', 'ur' => 'اردو (Urdu)'])
                        ->required(),
                    Forms\Components\Select::make('field')
                        ->options([
                            'name'              => 'Product name',
                            'short_description' => 'Short description',
                            'description'       => 'Full description',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('value')
                        ->rows(6)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                        ->columnSpanFull(),
                    Forms\Components\Select::make('status')
                        ->options([
                            ProductTranslation::STATUS_MISSING        => 'Missing',
                            ProductTranslation::STATUS_PENDING        => 'Pending',
                            ProductTranslation::STATUS_MACHINE_DRAFT  => 'Machine draft (internal)',
                            ProductTranslation::STATUS_HUMAN_REVIEWED => 'Human reviewed',
                            ProductTranslation::STATUS_APPROVED       => 'Approved (public)',
                            ProductTranslation::STATUS_REJECTED       => 'Rejected',
                            ProductTranslation::STATUS_STALE          => 'Stale (source changed)',
                        ])
                        ->required(),
                    Forms\Components\Select::make('source_provenance')
                        ->options([
                            ProductTranslation::SOURCE_MANUAL  => 'Manual',
                            ProductTranslation::SOURCE_IMPORT  => 'Import',
                            ProductTranslation::SOURCE_MACHINE => 'Machine',
                        ])
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('locale')->badge(),
                Tables\Columns\TextColumn::make('field')->badge(),
                Tables\Columns\TextColumn::make('value')->limit(50)->extraAttributes(['dir' => 'rtl']),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        ProductTranslation::STATUS_APPROVED       => 'success',
                        ProductTranslation::STATUS_STALE          => 'warning',
                        ProductTranslation::STATUS_REJECTED       => 'danger',
                        ProductTranslation::STATUS_PENDING,
                        ProductTranslation::STATUS_MACHINE_DRAFT,
                        ProductTranslation::STATUS_HUMAN_REVIEWED => 'gray',
                        default                                   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('source_provenance')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('reviewer.name')->label('Reviewer')->toggleable(),
                Tables\Columns\TextColumn::make('translated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    ProductTranslation::STATUS_MISSING        => 'Missing',
                    ProductTranslation::STATUS_PENDING        => 'Pending',
                    ProductTranslation::STATUS_MACHINE_DRAFT  => 'Machine draft',
                    ProductTranslation::STATUS_HUMAN_REVIEWED => 'Human reviewed',
                    ProductTranslation::STATUS_APPROVED       => 'Approved',
                    ProductTranslation::STATUS_REJECTED       => 'Rejected',
                    ProductTranslation::STATUS_STALE          => 'Stale',
                ]),
                Tables\Filters\SelectFilter::make('locale')->options(['ar' => 'Arabic', 'ur' => 'Urdu']),
                Tables\Filters\SelectFilter::make('field')->options([
                    'name' => 'Name', 'short_description' => 'Short description', 'description' => 'Full description',
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approve')
                    ->label('Approve selected')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $row) {
                            $row->update([
                                'status'      => ProductTranslation::STATUS_APPROVED,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }
                    }),
                Tables\Actions\BulkAction::make('reject')
                    ->label('Reject selected')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $row) {
                            $row->update([
                                'status'      => ProductTranslation::STATUS_REJECTED,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('translated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductTranslations::route('/'),
            'edit'  => Pages\EditProductTranslation::route('/{record}/edit'),
        ];
    }
}
