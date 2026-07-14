<?php
declare(strict_types=1);

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Domain\Support\SupportTicketService;
use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Phase 9 v9.1 — admin support-ticket VIEW page.
 *
 * Previously SupportTicketResource exposed only an Edit page, which meant
 * clicking a ticket row in the admin list opened a form that let the admin
 * overwrite the customer's original subject/body — data corruption risk +
 * confusing UX. v9.1 replaces it with a proper view page:
 *
 *   - Ticket detail rendered via an Infolist (read-only, never a form)
 *   - Message thread rendered chronologically with author + timestamp
 *   - Header actions: Reply, Change status, Change priority, Assign
 *
 * The customer's original message and subject are NEVER editable. Replies
 * are immutable rows in support_ticket_messages — each one has its own
 * author + timestamp + body. The SupportTicketService::reply() method
 * handles status transitions (staff reply → 'answered').
 */
class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    /**
     * Phase 9 v9.3 — eager-load every relation the Infolist renders BEFORE
     * Filament resolves the record. Without this, the Infolist's
     * RepeatableEntry::make('messages') accessed user.name per row and
     * Eloquent's strict mode raised:
     *
     *   LazyLoadingViolationException: Attempted to lazy load [user] on
     *   model [App\Models\SupportTicketMessage] but lazy loading is
     *   disabled.
     *
     * resolveRecord runs inside Filament's mount() before the Infolist is
     * built, so every chain accessed below is already loaded by the time
     * Filament reads it. The select clauses keep the payload small but
     * MUST include `id` (Eloquent's relation hydration needs the key).
     */
    public function resolveRecord(int | string $key): \Illuminate\Database\Eloquent\Model
    {
        return SupportTicket::query()
            ->with([
                'messages' => fn ($q) => $q->orderBy('created_at'),
                'messages.user:id,name,email',
                'user:id,name,email',
                'vendor:id,business_name',
                'order:id,number',
                'booking:id',
                'product:id,name',
                'assignee:id,name,email',
            ])
            ->findOrFail($key);
    }

    /**
     * Infolist — read-only display of the ticket. Subject and body are
     * shown as text, NOT as form inputs. There is no "edit" path from
     * this page to the customer's original content.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Ticket')->columns(2)->schema([
                Infolists\Components\TextEntry::make('number')->copyable()->weight('bold'),
                Infolists\Components\TextEntry::make('subject')->columnSpanFull(),
                Infolists\Components\TextEntry::make('ticket_type')->badge(),
                Infolists\Components\TextEntry::make('priority')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger', 'high' => 'warning',
                        'normal' => 'gray',   'low' => 'success',
                        default  => 'gray',
                    }),
                Infolists\Components\TextEntry::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'resolved' => 'success', 'closed' => 'gray',
                        'answered' => 'info', 'pending' => 'warning',
                        'open' => 'danger', default => 'gray',
                    }),
                Infolists\Components\TextEntry::make('user.email')->label('Customer'),
                Infolists\Components\TextEntry::make('assignee.email')->label('Assigned to')->default('—'),
                Infolists\Components\TextEntry::make('created_at')->dateTime()->label('Opened'),
                Infolists\Components\TextEntry::make('last_replied_at')->dateTime()->label('Last activity')->default('—'),
            ]),
            Infolists\Components\Section::make('Linked context')->columns(3)->visible(
                fn (SupportTicket $record) => $record->order_id || $record->vendor_id || $record->product_id
            )->schema([
                Infolists\Components\TextEntry::make('order.number')->label('Order')->default('—'),
                Infolists\Components\TextEntry::make('vendor.business_name')->label('Vendor')->default('—'),
                Infolists\Components\TextEntry::make('product.name')->label('Product')->default('—'),
            ]),
            Infolists\Components\Section::make('Conversation')->schema([
                Infolists\Components\RepeatableEntry::make('messages')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('author_role')->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'admin'    => 'info',
                                'vendor'   => 'warning',
                                'customer' => 'gray',
                                default    => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('user.name')->label('From'),
                        Infolists\Components\TextEntry::make('created_at')->dateTime()->label('At'),
                        Infolists\Components\TextEntry::make('body')->columnSpanFull()->markdown(),
                    ])->columns(3),
            ]),
        ]);
    }

    /**
     * Header actions — Reply, Change status, Change priority, Assign.
     *
     * Reply creates a NEW row in support_ticket_messages; it never
     * mutates the original customer message. SupportTicketService::reply()
     * also flips the ticket status to 'answered' so the customer queue
     * sorts correctly.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->visible(fn (SupportTicket $record) => ! in_array($record->status, ['closed', 'resolved'], true))
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label('Your reply')
                        ->required()
                        ->rows(6)
                        ->maxLength(5000),
                    Forms\Components\Toggle::make('is_internal')
                        ->label('Internal note (not visible to customer)')
                        ->default(false),
                ])
                ->action(function (array $data, SupportTicket $record, SupportTicketService $svc): void {
                    $svc->reply(
                        $record,
                        auth()->user(),
                        $data['body'],
                        SupportTicketMessage::ROLE_ADMIN,
                        (bool) ($data['is_internal'] ?? false),
                    );
                    // Phase 10 v10.11 §4 — explicit eager-load after mutation.
                    // resolveRecord eager-loads on mount; this re-loads after
                    // the action so Livewire's re-render of the Infolist's
                    // RepeatableEntry('messages') iterating message.user.name
                    // doesn't trigger LazyLoadingViolationException for the
                    // newly-created message row. The dev's confirmed error.
                    $record->load(['messages.user:id,name,email']);
                    Notification::make()->title('Reply posted')->success()->send();
                }),

            Actions\Action::make('changeStatus')
                ->label('Change status')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    Forms\Components\Select::make('status')
                        ->options([
                            'open'     => 'Open',
                            'pending'  => 'Pending',
                            'answered' => 'Answered',
                            'resolved' => 'Resolved',
                            'closed'   => 'Closed',
                        ])
                        ->required(),
                ])
                ->action(function (array $data, SupportTicket $record, SupportTicketService $svc): void {
                    $svc->updateStatus($record, $data['status']);
                    // v10.11 §4 — same defensive eager-load as the reply action
                    $record->load(['messages.user:id,name,email']);
                    Notification::make()->title('Status updated')->success()->send();
                }),

            Actions\Action::make('changePriority')
                ->label('Change priority')
                ->icon('heroicon-o-flag')
                ->form([
                    Forms\Components\Select::make('priority')
                        ->options([
                            'low' => 'Low', 'normal' => 'Normal',
                            'high' => 'High', 'urgent' => 'Urgent',
                        ])
                        ->required(),
                ])
                ->action(function (array $data, SupportTicket $record): void {
                    $record->update(['priority' => $data['priority']]);
                    // v10.11 §4 — defensive eager-load before Infolist re-render
                    $record->load(['messages.user:id,name,email']);
                    Notification::make()->title('Priority updated')->success()->send();
                }),

            Actions\Action::make('assign')
                ->label('Assign')
                ->icon('heroicon-o-user-plus')
                ->form([
                    Forms\Components\Select::make('assigned_to')
                        ->relationship('assignee', 'email')
                        ->searchable()
                        ->nullable(),
                ])
                ->action(function (array $data, SupportTicket $record): void {
                    $record->update(['assigned_to' => $data['assigned_to'] ?? null]);
                    // v10.11 §4 — defensive eager-load before Infolist re-render
                    $record->load(['messages.user:id,name,email']);
                    Notification::make()->title('Assignment updated')->success()->send();
                }),
        ];
    }

    /**
     * v9.1 — explicitly remove the inherited Edit header action that
     * would otherwise let the admin open the (now-deleted) edit form
     * and overwrite the customer's subject/body.
     */
    protected function hasFormActions(): bool
    {
        return false;
    }
}
