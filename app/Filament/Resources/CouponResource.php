<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Coupon')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')->required()->maxLength(50)
                        ->unique(ignoreRecord: true)
                        // Phase 9 v9.1 — Filament injects closure parameters BY NAME.
                        // The parameter name must be one of Filament's documented
                        // injection names (`$state`, `$record`, `$component`, `$get`,
                        // `$set`, `$livewire`, ...). Using `$s` raised:
                        //   BindingResolutionException: An attempt was made to evaluate
                        //   a closure for [Filament\Forms\Components\TextInput], but
                        //   [$s] was unresolvable.
                        ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),
                    Forms\Components\TextInput::make('currency')->default('KWD')->maxLength(3),
                    Forms\Components\Textarea::make('description')->columnSpanFull(),
                    Forms\Components\Select::make('discount_type')
                        ->options(['percentage' => 'Percentage', 'fixed_amount' => 'Fixed amount (minor units)'])
                        ->required(),
                    Forms\Components\TextInput::make('discount_value')->numeric()->required(),
                    Forms\Components\TextInput::make('min_order_minor')->numeric(),
                    Forms\Components\TextInput::make('max_discount_minor')->numeric(),
                    Forms\Components\DateTimePicker::make('starts_at'),
                    Forms\Components\DateTimePicker::make('ends_at'),
                    Forms\Components\TextInput::make('usage_limit')->numeric(),
                    Forms\Components\TextInput::make('per_user_limit')->numeric()->default(1)->required(),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Select::make('vendor_id')->relationship('vendor', 'business_name')->searchable()->preload(),
                    Forms\Components\Select::make('assigned_user_id')->relationship('assignedUser', 'email')->searchable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('discount_type')->badge(),
                Tables\Columns\TextColumn::make('discount_value'),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->default('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('usages_count')->counts('usages')->label('Uses'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
