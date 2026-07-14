<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\VendorSubscriptionResource\Pages;
use App\Models\VendorSubscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorSubscriptionResource extends Resource
{
    protected static ?string $model = VendorSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Subscription')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('vendor_id')->relationship('vendor', 'business_name')->searchable()->required(),
                    Forms\Components\Select::make('vendor_package_id')->relationship('package', 'name')->required(),
                    Forms\Components\DateTimePicker::make('starts_at')->required()->default(now()),
                    Forms\Components\DateTimePicker::make('ends_at'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active'    => 'Active',
                            'expired'   => 'Expired',
                            'cancelled' => 'Cancelled',
                            'grace'     => 'Grace period',
                            'pending'   => 'Pending',
                        ])
                        ->required(),
                    Forms\Components\Toggle::make('auto_renew'),
                    Forms\Components\TextInput::make('amount_paid_minor')->numeric()->minValue(0)->helperText('Integer minor units'),
                    Forms\Components\TextInput::make('currency')->default('KWD')->length(3),
                    Forms\Components\TextInput::make('payment_reference')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.business_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('package.name')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success', 'pending' => 'warning', 'expired' => 'danger',
                    'cancelled' => 'gray', 'grace' => 'warning', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('starts_at')->date(),
                Tables\Columns\TextColumn::make('ends_at')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('amount_paid_minor')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state, $record) => number_format(((int) $state) / 100, 2) . ' ' . $record->currency),
                Tables\Columns\IconColumn::make('auto_renew')->boolean()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'pending' => 'Pending', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'grace' => 'Grace']),
                Tables\Filters\SelectFilter::make('vendor_package_id')
                    ->label('Package')
                    ->relationship('package', 'name'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('starts_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVendorSubscriptions::route('/'),
            'create' => Pages\CreateVendorSubscription::route('/create'),
            'edit'   => Pages\EditVendorSubscription::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool { return auth()->user()?->can('vendor_subscriptions.manage') || auth()->user()?->can('vendors.view'); }
    public static function canCreate(): bool { return auth()->user()?->can('vendor_subscriptions.manage') ?? false; }
    public static function canEdit($r): bool { return auth()->user()?->can('vendor_subscriptions.manage') ?? false; }
}
