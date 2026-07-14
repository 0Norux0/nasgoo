<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Domain\Order\OrderLifecycleService;
use App\Domain\Payment\PaymentService;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * v6.2 — mirror the lifecycle header actions defined on ViewOrder so
     * admins find the same Confirm/Ship/Deliver/Cancel/Refund/COD-capture/
     * Transfer-confirm buttons whether they land on /admin/orders/{id} (view)
     * or /admin/orders/{id}/edit (edit).
     *
     * The list of orders in /admin/orders has both ViewAction and EditAction
     * row actions; depending on which the admin clicks, they land on either
     * page. v6.1 added the actions to ViewOrder only, which is why the dev
     * (who clicked "Edit") didn't see them.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm')
                ->label('Confirm')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn () => $this->record->status === Order::STATUS_PAID && auth()->user()?->can('orders.confirm'))
                ->requiresConfirmation()
                ->action(function (OrderLifecycleService $svc) {
                    $svc->confirm($this->record, auth()->user());
                    Notification::make()->title('Order confirmed.')->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('ship')
                ->label('Mark shipped')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn () => in_array($this->record->status, [Order::STATUS_PAID, Order::STATUS_CONFIRMED], true) && auth()->user()?->can('orders.ship'))
                ->requiresConfirmation()
                ->action(function (OrderLifecycleService $svc) {
                    $svc->markShipped($this->record, null, auth()->user());
                    Notification::make()->title('Order shipped.')->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('deliver')
                ->label('Mark delivered')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === Order::STATUS_SHIPPED && auth()->user()?->can('orders.deliver'))
                ->requiresConfirmation()
                ->action(function (OrderLifecycleService $svc) {
                    $svc->markDelivered($this->record, auth()->user());
                    Notification::make()->title('Order delivered. Earnings release scheduled in 7 days.')->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('cod_capture')
                ->label('Mark COD paid')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () =>
                    $this->record->payment_status === Order::PAY_PENDING
                    && auth()->user()?->can('payments.capture')
                    && $this->record->latestPayment?->method_slug === 'cod'
                )
                ->requiresConfirmation()
                ->action(function (PaymentService $svc) {
                    if ($this->record->latestPayment) {
                        $svc->capture($this->record->latestPayment);
                        Notification::make()->title('COD payment captured. Order marked paid.')->success()->send();
                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    }
                }),

            Actions\Action::make('capture_transfer')
                ->label('Confirm transfer')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->visible(fn () =>
                    $this->record->payment_status === Order::PAY_PENDING
                    && auth()->user()?->can('payments.capture')
                    && $this->record->latestPayment?->method_slug === 'manual_transfer'
                )
                ->requiresConfirmation()
                ->action(function (PaymentService $svc) {
                    if ($this->record->latestPayment) {
                        $svc->capture($this->record->latestPayment);
                        Notification::make()->title('Bank transfer confirmed. Order marked paid.')->success()->send();
                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    }
                }),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () =>
                    ! in_array($this->record->status, [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)
                    && auth()->user()?->can('orders.cancel')
                )
                ->form([Forms\Components\Textarea::make('reason')->required()->rows(3)])
                ->action(function (array $data, OrderLifecycleService $svc) {
                    $svc->cancel($this->record, $data['reason'], auth()->user());
                    Notification::make()->title('Order cancelled.')->warning()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('refund')
                ->label('Refund')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () =>
                    in_array($this->record->payment_status, [Order::PAY_PAID, Order::PAY_PARTIAL_REFUND], true)
                    && auth()->user()?->can('payments.refund')
                )
                ->form([
                    Forms\Components\TextInput::make('amount_minor')->numeric()->minValue(1)
                        ->helperText('Amount in minor units. Leave empty for full refund.'),
                    Forms\Components\Textarea::make('reason')->rows(2)->required(),
                ])
                ->action(function (array $data, PaymentService $svc) {
                    if (! $this->record->latestPayment) {
                        Notification::make()->title('No payment found.')->danger()->send();
                        return;
                    }
                    $svc->refund($this->record->latestPayment, $data['amount_minor'] ?? null, $data['reason']);
                    Notification::make()->title('Refund issued.')->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\ViewAction::make(),
        ];
    }

    /** v5.6 — same eager-load fix as ViewOrder; see that file for context. */
    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()
            ->whereKey($key)
            ->firstOrFail();
    }
}
