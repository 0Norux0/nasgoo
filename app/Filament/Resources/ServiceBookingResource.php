<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceBookingResource\Pages;
use App\Models\ServiceBooking;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Phase 8 — admin view of all service bookings across all vendors.
 * Read-mostly: admin can view + change status (eg. mark refunded after
 * a manual stripe refund). Vendor actions (accept/reject/complete) go
 * through the vendor controller, not the admin panel.
 */
class ServiceBookingResource extends Resource
{
    protected static ?string $model = ServiceBooking::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Service Bookings';
    protected static ?string $navigationGroup = 'Services';
    protected static ?int $navigationSort = 50;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Booking #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('product.name')->label('Service')->limit(40),
                Tables\Columns\TextColumn::make('provider.name')->label('Provider'),
                Tables\Columns\TextColumn::make('booked_for_date')->label('Date')->date()->sortable(),
                Tables\Columns\TextColumn::make('booked_for_time')->label('Time')->formatStateUsing(fn ($state) => $state ? substr((string) $state, 0, 5) : '—'),
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'warning' => ['pending', 'pending_payment'],
                    'success' => ['confirmed', 'accepted', 'completed'],
                    'danger'  => ['rejected', 'cancelled', 'no_show'],
                    'gray'    => ['refunded', 'rescheduled'],
                ]),
                Tables\Columns\TextColumn::make('price_minor')->label('Price')
                    ->formatStateUsing(fn ($state, $record) => number_format(((int) $state) / 100, 2) . ' ' . $record->currency),
                Tables\Columns\TextColumn::make('created_at')->label('Booked at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending', 'pending_payment' => 'Pending payment',
                        'confirmed' => 'Confirmed', 'accepted' => 'Accepted',
                        'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
                        'completed' => 'Completed', 'no_show' => 'No show',
                        'refunded' => 'Refunded', 'rescheduled' => 'Rescheduled',
                    ]),
            ])
            ->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()])
            ->defaultSort('booked_for_date', 'desc');
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Select::make('status')->options([
                'pending' => 'Pending', 'pending_payment' => 'Pending payment',
                'confirmed' => 'Confirmed', 'accepted' => 'Accepted',
                'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
                'completed' => 'Completed', 'no_show' => 'No show',
                'refunded' => 'Refunded', 'rescheduled' => 'Rescheduled',
            ])->required(),
            Textarea::make('vendor_notes')->rows(3),
            Textarea::make('rejection_reason')->rows(3),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceBookings::route('/'),
            'view'  => Pages\ViewServiceBooking::route('/{record}'),
            'edit'  => Pages\EditServiceBooking::route('/{record}/edit'),
        ];
    }

    /**
     * Phase 8 — eager-load every relation the table/view touches.
     * Same pattern as v7.6 OrderResource: forecloses lazy-load class.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'customer:id,name,email', 'vendor:id,business_name',
            'product:id,name,slug,type', 'provider:id,name,specialization',
            'order:id,number,payment_status',
        ]);
    }
}
