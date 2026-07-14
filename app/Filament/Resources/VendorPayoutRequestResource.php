<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Payout\PayoutService;
use App\Filament\Resources\VendorPayoutRequestResource\Pages;
use App\Models\VendorPayoutRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class VendorPayoutRequestResource extends Resource
{
    protected static ?string $model = VendorPayoutRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Vendors';
    protected static ?int $navigationSort = 5;
    protected static ?string $modelLabel = 'Payout Request';
    protected static ?string $pluralModelLabel = 'Payout Requests';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', VendorPayoutRequest::STATUS_PENDING)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('vendor_id')
                        ->relationship('vendor', 'business_name')->required()->disabled(),
                    Forms\Components\TextInput::make('requested_amount_minor')
                        ->numeric()->required()->disabled()
                        ->helperText('Stored in minor units (fils for KWD).'),
                    Forms\Components\TextInput::make('currency')->disabled(),
                    Forms\Components\TextInput::make('payout_method')->disabled(),
                    Forms\Components\KeyValue::make('payout_details')->disabled()->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Status')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('status')->disabled(),
                    Forms\Components\TextInput::make('transfer_reference')->maxLength(120),
                    Forms\Components\Textarea::make('admin_notes')->rows(2)->columnSpanFull(),
                    Forms\Components\Textarea::make('rejection_reason')->rows(2)->columnSpanFull()->disabled(),
                    Forms\Components\DateTimePicker::make('requested_at')->disabled(),
                    Forms\Components\DateTimePicker::make('approved_at')->disabled(),
                    Forms\Components\DateTimePicker::make('rejected_at')->disabled(),
                    Forms\Components\DateTimePicker::make('paid_at')->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('requested_amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record) => number_format(((int) $state) / 100, 3) . ' ' . $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        VendorPayoutRequest::STATUS_PENDING  => 'warning',
                        VendorPayoutRequest::STATUS_APPROVED => 'info',
                        VendorPayoutRequest::STATUS_REJECTED => 'danger',
                        VendorPayoutRequest::STATUS_PAID     => 'success',
                        default                              => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payout_method')->toggleable(),
                Tables\Columns\TextColumn::make('transfer_reference')->toggleable()->limit(20),
                Tables\Columns\TextColumn::make('requested_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->toggleable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    VendorPayoutRequest::STATUS_PENDING  => 'Pending',
                    VendorPayoutRequest::STATUS_APPROVED => 'Approved',
                    VendorPayoutRequest::STATUS_REJECTED => 'Rejected',
                    VendorPayoutRequest::STATUS_PAID     => 'Paid',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Action::make('approve')
                    ->visible(fn (VendorPayoutRequest $record) => $record->isPending())
                    ->icon('heroicon-o-check-circle')->color('info')
                    ->form([Forms\Components\Textarea::make('notes')->label('Admin notes')->rows(2)])
                    ->action(function (VendorPayoutRequest $record, array $data, PayoutService $svc) {
                        $svc->approve($record, auth()->user(), $data['notes'] ?? null);
                        Notification::make()->title('Payout request approved')->success()->send();
                    }),

                Action::make('reject')
                    ->visible(fn (VendorPayoutRequest $record) => $record->isPending())
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->form([Forms\Components\Textarea::make('reason')->label('Rejection reason')->required()->rows(2)])
                    ->action(function (VendorPayoutRequest $record, array $data, PayoutService $svc) {
                        $svc->reject($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Payout request rejected')->success()->send();
                    }),

                Action::make('markPaid')
                    ->label('Mark Paid')
                    ->visible(fn (VendorPayoutRequest $record) => $record->isApproved())
                    ->icon('heroicon-o-banknotes')->color('success')
                    ->form([Forms\Components\TextInput::make('transfer_reference')->label('Transfer reference')->required()->maxLength(120)])
                    ->action(function (VendorPayoutRequest $record, array $data, PayoutService $svc) {
                        $svc->markPaid($record, auth()->user(), $data['transfer_reference']);
                        Notification::make()->title('Payout marked as paid')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorPayoutRequests::route('/'),
            'view'  => Pages\ViewVendorPayoutRequest::route('/{record}'),
            'edit'  => Pages\EditVendorPayoutRequest::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin_staff']) ?? false;
    }

    public static function canCreate(): bool { return false; } // requested via vendor portal
    public static function canDelete($record): bool { return false; }
}
