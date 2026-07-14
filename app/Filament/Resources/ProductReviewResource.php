<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Review\ReviewService;
use App\Filament\Resources\ProductReviewResource\Pages;
use App\Models\ProductReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', ProductReview::STATUS_PENDING)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Review')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->relationship('product', 'name')->required()->disabled(),
                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'name')->required()->disabled(),
                    Forms\Components\TextInput::make('rating')->numeric()->minValue(1)->maxValue(5)->required(),
                    Forms\Components\Toggle::make('is_verified_purchase')->disabled(),
                    Forms\Components\TextInput::make('title')->maxLength(200),
                    Forms\Components\Textarea::make('body')->rows(4)->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Moderation')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            ProductReview::STATUS_PENDING  => 'Pending',
                            ProductReview::STATUS_APPROVED => 'Approved',
                            ProductReview::STATUS_REJECTED => 'Rejected',
                        ])->required(),
                    Forms\Components\Textarea::make('rejection_reason')->rows(2),
                    Forms\Components\DateTimePicker::make('approved_at')->disabled(),
                    Forms\Components\DateTimePicker::make('rejected_at')->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->limit(40)->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('rating')->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default     => 'danger',
                    }),
                Tables\Columns\TextColumn::make('title')->limit(40)->toggleable(),
                Tables\Columns\IconColumn::make('is_verified_purchase')->boolean()->label('Verified'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ProductReview::STATUS_PENDING  => 'warning',
                        ProductReview::STATUS_APPROVED => 'success',
                        ProductReview::STATUS_REJECTED => 'danger',
                        default                        => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        ProductReview::STATUS_PENDING  => 'Pending',
                        ProductReview::STATUS_APPROVED => 'Approved',
                        ProductReview::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Action::make('approve')
                    ->visible(fn (ProductReview $record) => $record->isPending())
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->action(function (ProductReview $record, ReviewService $svc) {
                        $svc->approve($record, auth()->user());
                        Notification::make()->title('Review approved')->success()->send();
                    }),
                Action::make('reject')
                    ->visible(fn (ProductReview $record) => $record->isPending())
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Rejection reason')->required()->rows(2),
                    ])
                    ->action(function (ProductReview $record, array $data, ReviewService $svc) {
                        $svc->reject($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Review rejected')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductReviews::route('/'),
            'view'  => Pages\ViewProductReview::route('/{record}'),
            'edit'  => Pages\EditProductReview::route('/{record}/edit'),
        ];
    }

    /**
     * Phase 9 v9.5 — eager-load the relations every page/action touches.
     *
     * Without this, the list page columns (`product.name`, `user.name`,
     * `vendor.business_name` accessed via the product) trigger lazy-loads
     * per row and strict mode (AppServiceProvider) throws. More importantly,
     * the row-level "approve" action receives a $record without the
     * `product` relation; ReviewService::approve then calls $review->product
     * which would also trip the strict guard and roll back the transaction
     * — that was the v9.5 review-display bug.
     *
     * ReviewService::approve also defensively loadMissing's `product` now,
     * so even direct service calls outside Filament are safe.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'product:id,name,slug,vendor_id,rating_avg,rating_count',
            'user:id,name,email',
            'orderItem:id,order_id,product_id',
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin_staff']) ?? false;
    }
}
