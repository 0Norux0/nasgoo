<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\VendorCommissionRuleResource\Pages;
use App\Models\VendorCommissionRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorCommissionRuleResource extends Resource
{
    protected static ?string $model = VendorCommissionRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Commission Rules';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Scope')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('vendor_id')
                        ->relationship('vendor', 'business_name')
                        ->searchable()
                        ->helperText('Leave empty for non-vendor-specific scopes.'),
                    Forms\Components\Select::make('scope')
                        ->options([
                            'global'           => 'Global',
                            'vendor'           => 'Vendor-specific',
                            'package'          => 'Package',
                            'category'         => 'Category',
                            'product'          => 'Product',
                            'service_category' => 'Service category',
                            'service'          => 'Service',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('scope_id')->numeric()->helperText('ID matching the chosen scope (e.g. package_id).'),
                    Forms\Components\Select::make('product_type')
                        ->options(['any' => 'Any', 'simple' => 'Simple', 'variable' => 'Variable', 'customizable' => 'Customizable', 'dropship' => 'Dropship', 'print_on_demand' => 'Print on demand', 'service' => 'Service'])
                        ->default('any')->required(),
                    Forms\Components\Select::make('payment_method')
                        ->options(['any' => 'Any', 'online' => 'Online', 'cod' => 'COD', 'wallet' => 'Wallet'])
                        ->default('any')->required(),
                    Forms\Components\Select::make('calculation_base')
                        ->options([
                            'selling_price'             => 'Selling price',
                            'net_profit_after_cost'     => 'Net profit (after cost)',
                            'subtotal_before_shipping'  => 'Subtotal before shipping',
                            'subtotal_after_discount'   => 'Subtotal after discount',
                            'service_fee'               => 'Service fee',
                            'booking_amount'            => 'Booking amount',
                            'promotion_fee'             => 'Promotion fee',
                        ])
                        ->default('selling_price')->required(),
                ]),

            Forms\Components\Section::make('Commission Value')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('commission_type')
                        ->options([
                            'percent'             => 'Percent',
                            'fixed'               => 'Fixed amount',
                            'fixed_plus_percent'  => 'Fixed + Percent',
                        ])
                        ->default('percent')->required()->live(),
                    Forms\Components\TextInput::make('percent_value')
                        ->numeric()->step('0.0001')->suffix('%')
                        ->visible(fn ($get) => in_array($get('commission_type'), ['percent', 'fixed_plus_percent'], true)),
                    Forms\Components\TextInput::make('fixed_value_minor')
                        ->numeric()->minValue(0)->suffix('minor units')
                        ->visible(fn ($get) => in_array($get('commission_type'), ['fixed', 'fixed_plus_percent'], true)),
                    Forms\Components\TextInput::make('currency')->default('KWD')->length(3),
                ]),

            Forms\Components\Section::make('Resolution')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('priority')->numeric()->default(100)
                        ->helperText('Lower value wins. Vendor rules typically 50, package 75, global 100.'),
                    Forms\Components\DateTimePicker::make('effective_from'),
                    Forms\Components\DateTimePicker::make('effective_until'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('scope')->badge(),
                Tables\Columns\TextColumn::make('product_type')->badge()->color('gray')->toggleable(),
                Tables\Columns\TextColumn::make('commission_type')->badge(),
                Tables\Columns\TextColumn::make('percent_value')->formatStateUsing(fn ($state) => $state !== null ? $state . '%' : '—'),
                Tables\Columns\TextColumn::make('fixed_value_minor')
                    ->label('Fixed')
                    ->formatStateUsing(fn ($state, $record) => $state !== null ? number_format(((int) $state) / 100, 2) . ' ' . $record->currency : '—'),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('scope'),
                Tables\Filters\SelectFilter::make('vendor_id')->relationship('vendor', 'business_name'),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('priority');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVendorCommissionRules::route('/'),
            'create' => Pages\CreateVendorCommissionRule::route('/create'),
            'edit'   => Pages\EditVendorCommissionRule::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool { return auth()->user()?->can('commissions.manage') || auth()->user()?->can('vendors.view'); }
    public static function canCreate(): bool { return auth()->user()?->can('commissions.manage') ?? false; }
    public static function canEdit($r): bool { return auth()->user()?->can('commissions.manage') ?? false; }
}
