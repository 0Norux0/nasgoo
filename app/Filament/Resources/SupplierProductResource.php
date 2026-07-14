<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Supplier\SupplierProductMapper;
use App\Filament\Resources\SupplierProductResource\Pages;
use App\Models\SupplierProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierProductResource extends Resource
{
    protected static ?string $model = SupplierProduct::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Supplier Products';
    protected static ?int $navigationSort = 62;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->disabled(),
            Forms\Components\Textarea::make('description')->disabled()->rows(4)->columnSpanFull(),
            Forms\Components\TextInput::make('external_product_id')->disabled(),
            Forms\Components\TextInput::make('external_sku')->disabled(),
            Forms\Components\TextInput::make('source_url')->disabled()->columnSpanFull(),
            Forms\Components\TextInput::make('supplier_cost_minor')->disabled()->prefix('minor units'),
            Forms\Components\TextInput::make('supplier_currency')->disabled(),
            Forms\Components\TextInput::make('supplier_stock_status')->disabled(),
            Forms\Components\TextInput::make('supplier_stock_qty')->disabled(),
            Forms\Components\TextInput::make('estimated_delivery_days')->disabled(),
            Forms\Components\TextInput::make('import_status')->disabled(),
            Forms\Components\Textarea::make('import_notes')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('imported_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->limit(40)->searchable()->wrap(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('platform.name')->label('Platform'),
                Tables\Columns\TextColumn::make('supplier_cost_minor')
                    ->label('Cost')
                    ->formatStateUsing(fn ($state, SupplierProduct $r) => number_format($state / 100, 2) . ' ' . $r->supplier_currency),
                Tables\Columns\TextColumn::make('import_status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('imported_at')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('import_status')->options([
                    SupplierProduct::STATUS_PENDING => 'Pending',
                    SupplierProduct::STATUS_MAPPED  => 'Mapped',
                    SupplierProduct::STATUS_PUBLISHED => 'Published',
                    SupplierProduct::STATUS_REJECTED  => 'Rejected',
                    SupplierProduct::STATUS_DISCONTINUED => 'Discontinued',
                ]),
                Tables\Filters\SelectFilter::make('platform')->relationship('platform', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (SupplierProduct $r) => $r->import_status === SupplierProduct::STATUS_MAPPED
                        && auth()->user()?->can('supplier_products.approve'))
                    ->requiresConfirmation()
                    ->action(function (SupplierProduct $r) {
                        app(SupplierProductMapper::class)->publish($r, auth()->user());
                        Notification::make()->title('Dropshipping product approved + published.')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SupplierProduct $r) => in_array($r->import_status, [SupplierProduct::STATUS_PENDING, SupplierProduct::STATUS_MAPPED], true)
                        && auth()->user()?->can('supplier_products.reject'))
                    ->form([Forms\Components\Textarea::make('reason')->required()->rows(3)])
                    ->action(function (array $data, SupplierProduct $r) {
                        app(SupplierProductMapper::class)->reject($r, $data['reason'], auth()->user());
                        Notification::make()->title('Supplier product rejected.')->warning()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['vendor', 'platform', 'integration', 'product']);
    }

    public static function canAccess(): bool { return auth()->user()?->can('supplier_products.view') ?? false; }
    public static function canCreate(): bool { return false; } // admin doesn't create; vendors import
    public static function canEdit($record): bool { return auth()->user()?->can('supplier_products.update') ?? false; }
    public static function canDelete($record): bool { return auth()->user()?->can('supplier_products.delete') ?? false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierProducts::route('/'),
            'view'  => Pages\ViewSupplierProduct::route('/{record}'),
            'edit'  => Pages\EditSupplierProduct::route('/{record}/edit'),
        ];
    }
}
