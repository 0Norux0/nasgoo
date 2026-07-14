<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Order\OrderLifecycleService;
use App\Domain\Payment\PaymentService;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', Order::STATUS_PENDING_PAYMENT)->count()
               + static::getModel()::where('status', Order::STATUS_PAID)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function form(Form $form): Form
    {
        // Orders are not editable in Filament — state changes go through the
        // OrderLifecycleService. The form is read-only display only.
        return $form->schema([
            Forms\Components\Section::make('Order')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('number')->disabled(),
                    Forms\Components\TextInput::make('status')->disabled(),
                    Forms\Components\TextInput::make('payment_status')->disabled(),
                    Forms\Components\TextInput::make('fulfillment_status')->disabled(),
                    Forms\Components\TextInput::make('currency')->disabled(),
                    Forms\Components\TextInput::make('total_minor')->disabled()
                        ->formatStateUsing(fn ($state, Order $record) => number_format($state / 100, 2) . ' ' . $record->currency),
                ]),
            Forms\Components\Section::make('Internal notes')
                ->schema([
                    Forms\Components\Textarea::make('internal_notes')->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending_payment' => 'warning',
                        'paid', 'confirmed' => 'info',
                        'shipped'           => 'primary',
                        'delivered', 'completed' => 'success',
                        'cancelled', 'failed'    => 'danger',
                        'refunded'          => 'gray',
                        default             => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_status')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('fulfillment_status')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, Order $record) => number_format($state / 100, 2) . ' ' . $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->getStateUsing(fn (Order $record) => $record->items->sum('quantity'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(array_combine(
                        ['pending_payment', 'paid', 'confirmed', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded', 'failed'],
                        ['Pending payment', 'Paid', 'Confirmed', 'Shipped', 'Delivered', 'Completed', 'Cancelled', 'Refunded', 'Failed'],
                    )),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options(array_combine(
                        ['pending', 'authorized', 'paid', 'failed', 'refunded', 'partially_refunded'],
                        ['Pending', 'Authorized', 'Paid', 'Failed', 'Refunded', 'Partially refunded'],
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->visible(fn (Order $record) => $record->status === Order::STATUS_PAID && auth()->user()?->can('orders.confirm'))
                    ->requiresConfirmation()
                    ->action(fn (Order $record, OrderLifecycleService $svc) => $svc->confirm($record, auth()->user()) && Notification::make()->title('Order confirmed.')->success()->send()),

                Tables\Actions\Action::make('ship')
                    ->label('Mark shipped')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn (Order $record) => in_array($record->status, [Order::STATUS_PAID, Order::STATUS_CONFIRMED], true) && auth()->user()?->can('orders.ship'))
                    ->requiresConfirmation()
                    ->action(fn (Order $record, OrderLifecycleService $svc) => $svc->markShipped($record, null, auth()->user()) && Notification::make()->title('Order shipped.')->success()->send()),

                Tables\Actions\Action::make('deliver')
                    ->label('Mark delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->status === Order::STATUS_SHIPPED && auth()->user()?->can('orders.deliver'))
                    ->requiresConfirmation()
                    ->action(fn (Order $record, OrderLifecycleService $svc) => $svc->markDelivered($record, auth()->user()) && Notification::make()->title('Order delivered. Earnings release scheduled in 7 days.')->success()->send()),

                Tables\Actions\Action::make('cod_capture')
                    ->label('Mark COD paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Order $record) =>
                        $record->payment_status === Order::PAY_PENDING
                        && auth()->user()?->can('payments.capture')
                        && $record->latestPayment?->method_slug === 'cod'
                    )
                    ->requiresConfirmation()
                    ->action(function (Order $record, PaymentService $svc) {
                        if ($record->latestPayment) {
                            $svc->capture($record->latestPayment);
                            Notification::make()->title('COD payment captured. Order marked paid.')->success()->send();
                        }
                    }),

                Tables\Actions\Action::make('capture_transfer')
                    ->label('Confirm transfer')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (Order $record) =>
                        $record->payment_status === Order::PAY_PENDING
                        && auth()->user()?->can('payments.capture')
                        && $record->latestPayment?->method_slug === 'manual_transfer'
                    )
                    ->requiresConfirmation()
                    ->action(function (Order $record, PaymentService $svc) {
                        if ($record->latestPayment) {
                            $svc->capture($record->latestPayment);
                            Notification::make()->title('Bank transfer confirmed. Order marked paid.')->success()->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) =>
                        ! in_array($record->status, [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)
                        && auth()->user()?->can('orders.cancel')
                    )
                    ->form([Forms\Components\Textarea::make('reason')->required()->rows(3)])
                    ->action(function (Order $record, array $data, OrderLifecycleService $svc) {
                        $svc->cancel($record, $data['reason'], auth()->user());
                        Notification::make()->title('Order cancelled.')->warning()->send();
                    }),

                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Order $record) =>
                        in_array($record->payment_status, [Order::PAY_PAID, Order::PAY_PARTIAL_REFUND], true)
                        && auth()->user()?->can('payments.refund')
                    )
                    ->form([
                        Forms\Components\TextInput::make('amount_minor')->numeric()->minValue(1)
                            ->helperText('Amount in minor units. Leave empty for full refund.'),
                        Forms\Components\Textarea::make('reason')->rows(2)->required(),
                    ])
                    ->action(function (Order $record, array $data, PaymentService $svc) {
                        if (! $record->latestPayment) {
                            Notification::make()->title('No payment found.')->danger()->send();
                            return;
                        }
                        $svc->refund($record->latestPayment, $data['amount_minor'] ?? null, $data['reason']);
                        Notification::make()->title('Refund issued.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool { return auth()->user()?->can('orders.view') ?? false; }
    public static function canCreate(): bool { return false; }  // orders only created via checkout
    public static function canDelete($record): bool { return false; }

    /**
     * v6.4 — comprehensive eager-load for EVERY relation accessed anywhere in
     * the OrderResource table closures + ViewOrder header actions + EditOrder
     * header actions. The full audit:
     *
     *   $record / $this->record relation reads in admin order code:
     *     ->items                  table column 'items_count' + view-page items table
     *     ->shippingAddress        view-page address block
     *     ->payments               view-page payment history
     *     ->latestPayment          ALL action visibility predicates +
     *                              COD-capture / Transfer-confirm / Refund actions
     *     ->user                   table column user.name (Customer)
     *     ->events                 view-page timeline + actor sub-relation
     *     ->shippingMethod         view-page shipping summary
     *
     * Previously this method eager-loaded only [items, shippingAddress, payments].
     * `latestPayment` is a SEPARATE relation (HasOne + latestOfMany) — loading
     * `payments` does NOT cover it. Every row in the admin orders list evaluates
     * COD-capture/Transfer-confirm visibility closures that read
     * $record->latestPayment?->method_slug → strict-mode lazy-load crash.
     *
     * ViewOrder and EditOrder pages override resolveRecord() to use this
     * exact query (v5.6), so fixing the query here fixes the pages too.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'user:id,name,email',
            'items',
            // v7.6 — Phase 7 customizable products. Filament ListOrders /
            // ViewOrder pages don't currently render customizations, but
            // any future column / infolist that touches $record->items->
            // customizations / latestProof would lazy-load under strict mode.
            // Eager-loading here forecloses that bug class.
            'items.customizations',
            'items.latestProof',
            'shippingAddress',
            'addresses',
            'payments',
            'latestPayment',
            'shippingMethod:id,name',
            'events.actor:id,name',
        ]);
    }
}
