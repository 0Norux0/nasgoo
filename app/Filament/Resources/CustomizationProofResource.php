<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CustomizationProofResource\Pages;
use App\Models\CustomizationProof;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomizationProofResource extends Resource
{
    protected static ?string $model = CustomizationProof::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Customization Proofs';
    protected static ?int $navigationSort = 70;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('file_original_name')->disabled(),
            Forms\Components\TextInput::make('file_mime')->disabled(),
            Forms\Components\TextInput::make('file_size_bytes')->disabled(),
            Forms\Components\Textarea::make('vendor_note')->disabled()->rows(3)->columnSpanFull(),
            Forms\Components\Textarea::make('customer_response')->disabled()->rows(3)->columnSpanFull(),
            Forms\Components\DateTimePicker::make('sent_at')->disabled(),
            Forms\Components\DateTimePicker::make('responded_at')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('orderItem.order.number')->label('Order')->searchable(),
                Tables\Columns\TextColumn::make('orderItem.product_name')->label('Product')->limit(30),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor'),
                Tables\Columns\TextColumn::make('file_original_name')->label('File')->limit(28),
                Tables\Columns\TextColumn::make('file_mime')->label('Type')->toggleable(),
                Tables\Columns\TextColumn::make('file_size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 0) . ' KB' : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray'    => CustomizationProof::STATUS_DRAFT,
                    'warning' => CustomizationProof::STATUS_SENT,
                    'success' => CustomizationProof::STATUS_APPROVED,
                    'danger'  => CustomizationProof::STATUS_REJECTED,
                ]),
                Tables\Columns\TextColumn::make('sent_at')->dateTime('Y-m-d H:i')->toggleable(),
                Tables\Columns\TextColumn::make('responded_at')->dateTime('Y-m-d H:i')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(
                    CustomizationProof::ALL_STATUSES,
                    array_map('ucfirst', CustomizationProof::ALL_STATUSES)
                )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // v6.4 lesson: eager-load every relation accessed in closures
        return parent::getEloquentQuery()->with([
            'orderItem.order:id,number',
            'vendor:id,business_name',
        ]);
    }

    public static function canAccess(): bool { return auth()->user()?->can('customization_proofs.view') ?? false; }
    public static function canCreate(): bool { return false; }   // vendor uploads — not admin
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomizationProofs::route('/'),
            'view'  => Pages\ViewCustomizationProof::route('/{record}'),
        ];
    }
}
