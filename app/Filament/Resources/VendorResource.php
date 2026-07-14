<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Vendor\VendorApprovalService;
use App\Filament\Resources\VendorResource\Pages;
use App\Models\Vendor;
use App\Models\VendorPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'business_name';

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', Vendor::STATUS_PENDING)->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Business')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('business_name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->maxLength(255)->helperText('Auto-generated if blank.'),
                    Forms\Components\TextInput::make('business_email')->email()->required(),
                    Forms\Components\TextInput::make('business_phone')->tel(),
                    Forms\Components\Select::make('business_type')->options(['individual' => 'Individual', 'company' => 'Company'])->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending'   => 'Pending review',
                            'approved'  => 'Approved',
                            'rejected'  => 'Rejected',
                            'suspended' => 'Suspended',
                            'closed'    => 'Closed',
                        ])
                        ->required()
                        ->helperText('Use the approval actions in the table instead of editing directly when possible.'),
                    Forms\Components\Textarea::make('description')->rows(4)->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Owner')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('user_id')->relationship('user', 'name')->searchable()->required(),
                    Forms\Components\TextInput::make('owner_name'),
                    Forms\Components\TextInput::make('owner_email')->email(),
                    Forms\Components\TextInput::make('owner_phone')->tel(),
                ]),

            Forms\Components\Section::make('Location')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('country')->length(2)->default('KW'),
                    Forms\Components\TextInput::make('city'),
                    Forms\Components\Textarea::make('address')->rows(2)->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Legal & Identity')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('commercial_license_no'),
                    Forms\Components\TextInput::make('tax_id'),
                    Forms\Components\TextInput::make('civil_id'),
                ]),

            Forms\Components\Section::make('Documents')
                ->columns(2)
                ->collapsed(false)
                ->schema([
                    // Phase 10 v10.1 — show actual viewable previews instead
                    // of raw paths. Public 'logo'/'banner' use the standard
                    // Storage::url. 'license_document'/'id_document' live on
                    // the private vendors disk and are served via the
                    // /admin/vendor-files/{id}/{kind} signed-URL controller
                    // so admins (and only admins) can view them without
                    // exposing the file publicly. Empty paths show a
                    // friendly fallback instead of a blank field.
                    Forms\Components\Placeholder::make('logo_view')
                        ->label('Logo')
                        ->content(fn (?\App\Models\Vendor $record) => $record
                            ? \App\Domain\Vendor\VendorFileLinks::previewHtml($record, 'logo')
                            : '—')
                        ->columnSpan(1)
                        ->extraAttributes(['data-v103' => 'vendor-file-preview']),
                    Forms\Components\Placeholder::make('banner_view')
                        ->label('Banner')
                        ->content(fn (?\App\Models\Vendor $record) => $record
                            ? \App\Domain\Vendor\VendorFileLinks::previewHtml($record, 'banner')
                            : '—')
                        ->columnSpan(1)
                        ->extraAttributes(['data-v103' => 'vendor-file-preview']),
                    Forms\Components\Placeholder::make('license_view')
                        ->label('Business license')
                        ->content(fn (?\App\Models\Vendor $record) => $record
                            ? \App\Domain\Vendor\VendorFileLinks::previewHtml($record, 'license_document')
                            : '—')
                        ->columnSpan(1)
                        ->extraAttributes(['data-v103' => 'vendor-file-preview']),
                    Forms\Components\Placeholder::make('id_view')
                        ->label('ID / passport')
                        ->content(fn (?\App\Models\Vendor $record) => $record
                            ? \App\Domain\Vendor\VendorFileLinks::previewHtml($record, 'id_document')
                            : '—')
                        ->columnSpan(1)
                        ->extraAttributes(['data-v103' => 'vendor-file-preview']),
                ]),

            // Phase 10 v10.1 — show what package the vendor selected at
            // application time. Pre-v10.1 this was hidden in the approve
            // action's default dropdown; admins viewing the vendor record
            // had no way to see the selection. Now displayed prominently.
            Forms\Components\Section::make('Vendor-selected package (from application)')
                ->columns(2)
                ->collapsed(false)
                ->schema([
                    Forms\Components\Placeholder::make('requested_package')
                        ->label('Requested package')
                        ->content(function (?\App\Models\Vendor $record) {
                            if (! $record) return '—';
                            $latest = $record->subscriptions()
                                ->with('package')
                                ->orderByDesc('id')
                                ->first();
                            if (! $latest || ! $latest->package) {
                                return 'No package selected (or package deleted).';
                            }
                            $p = $latest->package;
                            $statusBadge = match ($latest->status) {
                                'pending'   => '⏳ Pending (vendor selected — awaiting approval)',
                                'active'    => '✅ Active subscription',
                                'cancelled' => '❌ Cancelled',
                                'expired'   => '⌛ Expired',
                                default     => $latest->status,
                            };
                            return new \Illuminate\Support\HtmlString(sprintf(
                                '<div class="space-y-1">'
                                . '<div class="font-semibold text-base">%s</div>'
                                . '<div class="text-sm text-slate-600">%s</div>'
                                . '<div class="text-sm">Max products: <strong>%d</strong> · Default commission: <strong>%s%%</strong></div>'
                                . '<div class="text-xs text-slate-500">Selected on %s</div>'
                                . '</div>',
                                e($p->name),
                                e($statusBadge),
                                (int) $p->max_products,
                                e((string) $p->default_admin_commission_percent),
                                e($latest->starts_at?->toDateString() ?? '—'),
                            ));
                        })
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Payout')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('payout_method'),
                    Forms\Components\Textarea::make('payout_details')
                        ->helperText('Stored encrypted at rest. JSON object.')
                        ->rows(3),
                ]),

            Forms\Components\Section::make('Admin')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('featured'),
                    Forms\Components\DateTimePicker::make('featured_until'),
                    Forms\Components\Textarea::make('admin_notes')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('rejection_reason')->rows(2)->columnSpanFull()->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.email')->label('Owner email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('country')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('city')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        'suspended' => 'gray',
                        'closed'    => 'gray',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('activeSubscription.package.name')
                    ->label('Active package')
                    ->placeholder('— none active —')
                    ->toggleable(),
                // Phase 10 v10.1 — show what the vendor SELECTED at application
                // time. Uses a virtual accessor that returns the latest
                // VendorSubscription's package name regardless of status.
                // For approved vendors this usually matches Active package;
                // for pending applicants it shows their chosen tier even
                // though no active subscription exists yet.
                Tables\Columns\TextColumn::make('latest_requested_package')
                    ->label('Requested package')
                    ->placeholder('—')
                    ->toggleable()
                    ->getStateUsing(function (\App\Models\Vendor $r): string {
                        $latest = $r->subscriptions()->with('package')->orderByDesc('id')->first();
                        if (! $latest || ! $latest->package) return '—';
                        return $latest->package->name . ' (' . $latest->status . ')';
                    }),
                Tables\Columns\IconColumn::make('featured')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('approved_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'approved'  => 'Approved',
                        'rejected'  => 'Rejected',
                        'suspended' => 'Suspended',
                        'closed'    => 'Closed',
                    ])
                    ->default('pending'),
                Tables\Filters\SelectFilter::make('country'),
                Tables\Filters\TernaryFilter::make('featured'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Vendor $record) => $record->status === Vendor::STATUS_PENDING && auth()->user()?->can('vendors.approve'))
                    ->form([
                        Forms\Components\Select::make('vendor_package_id')
                            ->label('Assign package')
                            ->options(VendorPackage::where('is_active', true)->pluck('name', 'id'))
                            ->default(fn (Vendor $record) => $record->subscriptions()->latest('id')->value('vendor_package_id'))
                            ->required(),
                    ])
                    ->action(function (Vendor $record, array $data, VendorApprovalService $svc) {
                        $package = VendorPackage::findOrFail($data['vendor_package_id']);
                        $svc->approve($record, $package);
                        Notification::make()->title('Vendor approved')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Vendor $record) => $record->status === Vendor::STATUS_PENDING && auth()->user()?->can('vendors.approve'))
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->rows(3),
                    ])
                    ->action(function (Vendor $record, array $data, VendorApprovalService $svc) {
                        $svc->reject($record, $data['reason']);
                        Notification::make()->title('Vendor rejected')->warning()->send();
                    }),

                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (Vendor $record) => $record->status === Vendor::STATUS_APPROVED && auth()->user()?->can('vendors.suspend'))
                    ->form([
                        Forms\Components\Textarea::make('reason')->rows(3),
                    ])
                    ->action(function (Vendor $record, array $data, VendorApprovalService $svc) {
                        $svc->suspend($record, $data['reason'] ?? null);
                        Notification::make()->title('Vendor suspended')->warning()->send();
                    }),

                Tables\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Vendor $record) => in_array($record->status, [Vendor::STATUS_REJECTED, Vendor::STATUS_SUSPENDED, Vendor::STATUS_CLOSED], true) && auth()->user()?->can('vendors.approve'))
                    ->requiresConfirmation()
                    ->action(function (Vendor $record, VendorApprovalService $svc) {
                        $svc->reopen($record);
                        Notification::make()->title('Vendor returned to pending review')->info()->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with('activeSubscription.package')
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit'   => Pages\EditVendor::route('/{record}/edit'),
            'view'   => Pages\ViewVendor::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('vendors.view') ?? false;
    }
}
