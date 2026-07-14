<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Supplier\DropshipOrderCreator;
use App\Filament\Resources\SupplierOrderResource\Pages;
use App\Models\SupplierOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierOrderResource extends Resource
{
    protected static ?string $model = SupplierOrder::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationLabel = 'Supplier Orders';
    protected static ?int $navigationSort = 63;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('supplier_reference')->maxLength(120),
            Forms\Components\TextInput::make('tracking_number')->maxLength(120),
            Forms\Components\TextInput::make('tracking_url')->url()->maxLength(1024)->columnSpanFull(),
            Forms\Components\TextInput::make('carrier')->maxLength(80),
            Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('platform.name')->label('Platform'),
                Tables\Columns\TextColumn::make('order.number')->label('Order'),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('supplier_reference')->toggleable(),
                Tables\Columns\TextColumn::make('tracking_number')->toggleable(),
                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, SupplierOrder $r) => number_format($state / 100, 2) . ' ' . $r->currency),
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(
                    SupplierOrder::ALL_STATUSES,
                    array_map('ucfirst', SupplierOrder::ALL_STATUSES)
                )),
                Tables\Filters\SelectFilter::make('platform')->relationship('platform', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mark_placed')
                    ->label('Mark placed')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (SupplierOrder $r) => $r->status === SupplierOrder::STATUS_PENDING && auth()->user()?->can('supplier_orders.update'))
                    ->requiresConfirmation()
                    ->action(function (SupplierOrder $r) {
                        app(DropshipOrderCreator::class)->transition($r, SupplierOrder::STATUS_PLACED, auth()->id(), 'admin');
                        Notification::make()->title('Supplier order marked placed.')->success()->send();
                    }),
                Tables\Actions\Action::make('mark_shipped')
                    ->label('Mark shipped')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn (SupplierOrder $r) => in_array($r->status, [SupplierOrder::STATUS_PLACED, SupplierOrder::STATUS_PACKED], true)
                        && auth()->user()?->can('supplier_orders.update'))
                    ->requiresConfirmation()
                    ->action(function (SupplierOrder $r) {
                        app(DropshipOrderCreator::class)->transition($r, SupplierOrder::STATUS_SHIPPED, auth()->id(), 'admin');
                        Notification::make()->title('Supplier order marked shipped.')->success()->send();
                    }),
                Tables\Actions\Action::make('mark_delivered')
                    ->label('Mark delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SupplierOrder $r) => $r->status === SupplierOrder::STATUS_SHIPPED && auth()->user()?->can('supplier_orders.update'))
                    ->requiresConfirmation()
                    ->action(function (SupplierOrder $r) {
                        app(DropshipOrderCreator::class)->transition($r, SupplierOrder::STATUS_DELIVERED, auth()->id(), 'admin');
                        Notification::make()->title('Supplier order marked delivered.')->success()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // v6.4 lesson: eager-load every relation accessed in closures.
        return parent::getEloquentQuery()->with(['vendor', 'platform', 'order:id,number', 'orderItems', 'events.actor:id,name']);
    }

    public static function canAccess(): bool { return auth()->user()?->can('supplier_orders.view') ?? false; }
    public static function canCreate(): bool { return false; } // created automatically by checkout
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierOrders::route('/'),
            'view'  => Pages\ViewSupplierOrder::route('/{record}'),
            'edit'  => Pages\EditSupplierOrder::route('/{record}/edit'),
        ];
    }
}
