<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;
    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';
    protected static ?string $navigationGroup = 'Support';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Ticket')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('number')->disabled(),
                    Forms\Components\Select::make('user_id')->relationship('user', 'email')->searchable()->required(),
                    Forms\Components\TextInput::make('subject')->required()->columnSpanFull(),
                    Forms\Components\Select::make('ticket_type')
                        ->options([
                            'order_issue' => 'Order issue',
                            'booking_issue' => 'Booking issue',
                            'payment_issue' => 'Payment issue',
                            'product_issue' => 'Product issue',
                            'vendor_complaint' => 'Vendor complaint',
                            'refund_request' => 'Refund request',
                            'general_inquiry' => 'General inquiry',
                        ])->required(),
                    Forms\Components\Select::make('priority')
                        ->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'open' => 'Open', 'pending' => 'Pending', 'answered' => 'Answered',
                            'resolved' => 'Resolved', 'closed' => 'Closed',
                        ])->required(),
                    Forms\Components\Select::make('assigned_to')->relationship('assignee', 'email')->searchable(),
                ]),
            Forms\Components\Section::make('Related context')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('order_id')->relationship('order', 'number')->searchable(),
                    Forms\Components\Select::make('vendor_id')->relationship('vendor', 'business_name')->searchable(),
                    Forms\Components\Select::make('product_id')->relationship('product', 'name')->searchable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('user.email')->label('From'),
                Tables\Columns\TextColumn::make('ticket_type')->badge(),
                Tables\Columns\TextColumn::make('priority')->badge()->colors([
                    'success' => 'low', 'gray' => 'normal', 'warning' => 'high', 'danger' => 'urgent',
                ]),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('last_replied_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open', 'pending' => 'Pending', 'answered' => 'Answered',
                    'resolved' => 'Resolved', 'closed' => 'Closed',
                ]),
                Tables\Filters\SelectFilter::make('priority')->options([
                    'low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent',
                ]),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->defaultSort('last_replied_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            // Phase 9 v9.1 — view, not edit. Admin replies via the
            // "Reply" header action which creates a new immutable message
            // row, never mutates the customer's original subject/body.
            'view'  => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }

    /**
     * Phase 9 v9.3 — eager-load the relations the list-page table columns
     * touch (user.email, vendor.business_name, order.number). Without this,
     * each row in the list triggers up to 3 lazy-loads → strict mode throws.
     *
     * This applies to the LIST page only. The View page additionally
     * eager-loads `messages.user` via ViewSupportTicket::resolveRecord()
     * because the Infolist's RepeatableEntry renders messages.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'user:id,name,email',
            'vendor:id,business_name',
            'order:id,number',
        ]);
    }
}
