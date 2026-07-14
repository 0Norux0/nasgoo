<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Product\ProductPublishingService;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', Product::STATUS_PENDING_REVIEW)->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('vendor_id')->relationship('vendor', 'business_name')->searchable()->required(),
                    Forms\Components\Select::make('category_id')->relationship('category', 'name')->searchable(),
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('slug')->helperText('Auto-generated if blank.'),
                    Forms\Components\TextInput::make('sku'),
                    Forms\Components\Select::make('type')
                        ->options([
                            Product::TYPE_SIMPLE   => 'Simple',
                            Product::TYPE_VARIABLE => 'Variable',
                            Product::TYPE_DIGITAL  => 'Digital',
                        ])
                        ->default(Product::TYPE_SIMPLE)->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            Product::STATUS_DRAFT          => 'Draft',
                            Product::STATUS_PENDING_REVIEW => 'Pending review',
                            Product::STATUS_PUBLISHED      => 'Published',
                            Product::STATUS_REJECTED       => 'Rejected',
                            Product::STATUS_ARCHIVED       => 'Archived',
                        ])
                        ->required()
                        ->helperText('Use the row actions for state transitions when possible.'),
                ]),

            Forms\Components\Section::make('Description')
                ->schema([
                    Forms\Components\Textarea::make('short_description')->rows(2),
                    Forms\Components\Textarea::make('description')->rows(6),
                ]),

            // Phase 11B.1 v11B.1.1 §4 — Arabic translations.
            // Filament's dot-keyed names write into the JSON-cast columns;
            // because name_translations / short_description_translations /
            // description_translations are `array` casts, Filament round-trips
            // the `.ar` path correctly. Leaving any field blank stores no
            // 'ar' key, so the model's translatedName() / translatedDescription()
            // fall back to the English value (controlled fallback per dev §3).
            Forms\Components\Section::make('Arabic translations (optional)')
                ->description('عربي. Leave any field blank to fall back to English. RTL input direction is applied.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('name_translations.ar')
                        ->label('Product name (Arabic)')
                        ->maxLength(255)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                        ->placeholder('اسم المنتج'),
                    Forms\Components\Textarea::make('short_description_translations.ar')
                        ->label('Short description (Arabic)')
                        ->rows(2)
                        ->maxLength(500)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                        ->placeholder('وصف قصير بالعربية'),
                    Forms\Components\Textarea::make('description_translations.ar')
                        ->label('Full description (Arabic)')
                        ->rows(6)
                        ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                        ->placeholder('وصف كامل بالعربية'),
                ]),

            Forms\Components\Section::make('Pricing & Inventory')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('price_minor')->numeric()->minValue(0)
                        ->helperText('Integer minor units (e.g. 1000 = 1.000 KWD).')->required(),
                    Forms\Components\TextInput::make('compare_at_price_minor')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('cost_price_minor')->numeric()->minValue(0)->helperText('Internal — used for profit reports.'),
                    Forms\Components\TextInput::make('currency')->default('KWD')->length(3),
                    Forms\Components\Toggle::make('track_stock')->default(true),
                    Forms\Components\TextInput::make('stock')->numeric()->default(0),
                    Forms\Components\TextInput::make('weight_grams')->numeric()->helperText('Optional, for shipping in Phase 4+.'),
                ]),

            Forms\Components\Section::make('Images')
                ->description('JPG, PNG, or WEBP. Up to 10 images, 5 MB each. The first image is the primary thumbnail.')
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->relationship('images')
                        ->orderColumn('position')
                        ->schema([
                            Forms\Components\FileUpload::make('path')
                                ->label('Image')
                                ->image()
                                ->disk(config('marketplace.media_disk', 'public'))
                                ->directory('products/admin')
                                ->visibility('public')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(5120)
                                ->imageEditor()
                                ->required(),
                            Forms\Components\TextInput::make('alt_text')->label('Alt text')->maxLength(160),
                            Forms\Components\Toggle::make('is_primary')->label('Primary'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel('Add image')
                        ->collapsible(),
                ]),

            Forms\Components\Section::make('Storefront')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\Toggle::make('featured'),
                    Forms\Components\DateTimePicker::make('featured_until'),
                    Forms\Components\TextInput::make('meta_title'),
                    Forms\Components\Textarea::make('meta_description')->rows(2)->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Review notes')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('rejection_reason')->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primaryImage.path')
                    ->label('')
                    ->disk(config('marketplace.media_disk', 'public'))
                    ->height(40)
                    ->square()
                    ->defaultImageUrl(url('/images/placeholder-product.svg')),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('category.name')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'draft'          => 'gray',
                        'pending_review' => 'warning',
                        'published'      => 'success',
                        'rejected'       => 'danger',
                        'archived'       => 'gray',
                        default          => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price_minor')
                    ->label('Price')
                    ->formatStateUsing(fn ($state, Product $record) => number_format($state / 100, 2) . ' ' . $record->currency),
                Tables\Columns\TextColumn::make('stock')->toggleable(),
                Tables\Columns\IconColumn::make('featured')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Product::STATUS_DRAFT          => 'Draft',
                        Product::STATUS_PENDING_REVIEW => 'Pending review',
                        Product::STATUS_PUBLISHED      => 'Published',
                        Product::STATUS_REJECTED       => 'Rejected',
                        Product::STATUS_ARCHIVED       => 'Archived',
                    ])
                    ->default(Product::STATUS_PENDING_REVIEW),
                Tables\Filters\SelectFilter::make('vendor_id')->relationship('vendor', 'business_name')->searchable(),
                Tables\Filters\SelectFilter::make('category_id')->relationship('category', 'name')->searchable(),
                Tables\Filters\TernaryFilter::make('featured'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Product $record) => $record->isPendingReview() && auth()->user()?->can('products.publish'))
                    ->requiresConfirmation()
                    ->action(function (Product $record, ProductPublishingService $svc) {
                        $svc->publish($record);
                        Notification::make()->title('Product published')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Product $record) => $record->isPendingReview() && auth()->user()?->can('products.publish'))
                    ->form([Forms\Components\Textarea::make('reason')->required()->rows(3)])
                    ->action(function (Product $record, array $data, ProductPublishingService $svc) {
                        $svc->reject($record, $data['reason']);
                        Notification::make()->title('Product rejected')->warning()->send();
                    }),

                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn (Product $record) => $record->isPublished() && auth()->user()?->can('products.publish'))
                    ->requiresConfirmation()
                    ->action(function (Product $record, ProductPublishingService $svc) {
                        $svc->archive($record);
                        Notification::make()->title('Product archived')->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['vendor:id,business_name', 'category:id,name'])
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
            'view'   => Pages\ViewProduct::route('/{record}'),
        ];
    }

    public static function canAccess(): bool { return auth()->user()?->can('products.view') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->can('products.create') ?? false; }
    public static function canEdit($record): bool { return auth()->user()?->can('products.update') ?? false; }
    public static function canDelete($record): bool { return auth()->user()?->can('products.delete') ?? false; }
}
