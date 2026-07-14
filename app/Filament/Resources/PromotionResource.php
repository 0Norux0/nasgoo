<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Promotion')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('description')->columnSpanFull(),
                    Forms\Components\Select::make('promotion_type')
                        ->options([
                            'deal_of_day' => 'Deal of the Day',
                            'flash_sale' => 'Flash Sale',
                            'limited_time' => 'Limited Time',
                            'category' => 'Category',
                            'vendor' => 'Vendor',
                            'product_specific' => 'Product Specific',
                            'free_shipping' => 'Free Shipping',
                            'service_specific' => 'Service Specific',
                        ])->required(),
                    Forms\Components\Select::make('discount_type')
                        ->options([
                            'percentage' => 'Percentage',
                            'fixed_amount' => 'Fixed amount (minor units)',
                            'free_shipping' => 'Free shipping',
                        ])->required(),
                    Forms\Components\TextInput::make('discount_value')->numeric()->required(),
                    Forms\Components\TextInput::make('currency')->default('KWD')->maxLength(3),
                    Forms\Components\DateTimePicker::make('starts_at'),
                    Forms\Components\DateTimePicker::make('ends_at'),
                    Forms\Components\TextInput::make('min_order_minor')->numeric()->label('Min order (minor units)'),
                    Forms\Components\TextInput::make('max_discount_minor')->numeric()->label('Max discount (minor units)'),
                    Forms\Components\TextInput::make('usage_limit')->numeric(),
                    Forms\Components\TextInput::make('per_customer_limit')->numeric(),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ]),
            Forms\Components\Section::make('Vendor / approval')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('vendor_id')->relationship('vendor', 'business_name')->searchable()->preload(),
                    Forms\Components\Select::make('approval_status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])->default('approved')->required(),
                    Forms\Components\Textarea::make('rejection_reason')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('promotion_type')->badge(),
                Tables\Columns\TextColumn::make('discount_type'),
                Tables\Columns\TextColumn::make('discount_value'),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->default('—'),
                Tables\Columns\TextColumn::make('approval_status')->badge()->colors([
                    'success' => 'approved', 'warning' => 'pending', 'danger' => 'rejected',
                ]),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('approval_status')->options([
                    'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected',
                ]),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
