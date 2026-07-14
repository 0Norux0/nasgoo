<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPackageResource\Pages;
use App\Models\VendorPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorPackageResource extends Resource
{
    protected static ?string $model = VendorPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Vendor Packages';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('slug')->helperText('Auto from name if blank.'),
                    Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Pricing')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('price_minor')->numeric()->minValue(0)
                        ->helperText('Integer minor units. 5000 = 5.000 KWD'),
                    Forms\Components\TextInput::make('currency')->default('KWD')->length(3),
                    Forms\Components\Select::make('billing_cycle')
                        ->options(['monthly' => 'Monthly', 'yearly' => 'Yearly', 'lifetime' => 'Lifetime'])
                        ->required(),
                    Forms\Components\TextInput::make('trial_days')->numeric()->minValue(0)->default(0),
                ]),

            Forms\Components\Section::make('Limits')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('max_products')->numeric()->minValue(0)->helperText('Empty = unlimited'),
                    Forms\Components\TextInput::make('max_services')->numeric()->minValue(0)->helperText('Empty = unlimited'),
                    Forms\Components\TextInput::make('max_images_per_product')->numeric()->minValue(1)->required(),
                ]),

            Forms\Components\Section::make('Features')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('allow_video'),
                    Forms\Components\Toggle::make('allow_3d'),
                    Forms\Components\Toggle::make('allow_dropshipping'),
                    Forms\Components\Toggle::make('allow_product_import'),
                    Forms\Components\Toggle::make('allow_customization'),
                    Forms\Components\Toggle::make('allow_services'),
                    Forms\Components\Toggle::make('allow_promotions'),
                    Forms\Components\Toggle::make('allow_deal_of_day'),
                    Forms\Components\Toggle::make('allow_featured_vendor'),
                ]),

            Forms\Components\Section::make('Commission & Visibility')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('analytics_level')
                        ->options(['basic' => 'Basic', 'standard' => 'Standard', 'advanced' => 'Advanced']),
                    Forms\Components\TextInput::make('default_admin_commission_percent')
                        ->numeric()->step('0.01')->minValue(0)->maxValue(100)->suffix('%'),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('price_minor')
                    ->label('Price')
                    ->formatStateUsing(fn ($state, VendorPackage $record) => number_format($state / 100, 2) . ' ' . $record->currency),
                Tables\Columns\TextColumn::make('billing_cycle')->badge(),
                Tables\Columns\TextColumn::make('default_admin_commission_percent')
                    ->label('Commission %')
                    ->formatStateUsing(fn ($state) => $state . '%'),
                Tables\Columns\TextColumn::make('subscriptions_count')->counts('subscriptions')->label('Subscribers'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVendorPackages::route('/'),
            'create' => Pages\CreateVendorPackage::route('/create'),
            'edit'   => Pages\EditVendorPackage::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool { return auth()->user()?->can('vendor_packages.manage') || auth()->user()?->can('vendors.view'); }
    public static function canCreate(): bool { return auth()->user()?->can('vendor_packages.manage') ?? false; }
    public static function canEdit($record): bool { return auth()->user()?->can('vendor_packages.manage') ?? false; }
    public static function canDelete($record): bool { return auth()->user()?->can('vendor_packages.manage') ?? false; }
}
