<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context) => $context === 'create')
                        ->minLength(8)
                        ->helperText('Leave empty when editing to keep the existing password.'),
                ]),

            Forms\Components\Section::make('Profile')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('locale')
                        ->options(['en' => 'English', 'ar' => 'العربية', 'ur' => 'اردو'])
                        ->default('en'),
                    Forms\Components\Select::make('default_currency')
                        ->options(['KWD' => 'KWD', 'USD' => 'USD', 'AED' => 'AED', 'PKR' => 'PKR'])
                        ->default('KWD'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active'    => 'Active',
                            'suspended' => 'Suspended',
                            'banned'    => 'Banned',
                        ])
                        ->required()
                        ->default('active'),
                ]),

            Forms\Components\Section::make('Roles')
                ->schema([
                    Forms\Components\CheckboxList::make('roles')
                        ->relationship('roles', 'name')
                        ->columns(2)
                        ->bulkToggleable()
                        ->helperText('Vendors and customers cannot access the admin panel.'),
                ]),

            Forms\Components\Section::make('Verification')
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('email_verified_at')
                        ->helperText('Set to mark email as verified manually.'),
                    Forms\Components\DateTimePicker::make('phone_verified_at'),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin_staff' => 'warning',
                        'vendor'      => 'info',
                        'customer'    => 'gray',
                        default       => 'gray',
                    })
                    ->separator(','),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'suspended' => 'warning',
                        'banned'    => 'danger',
                        default     => 'gray',
                    }),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'    => 'Active',
                        'suspended' => 'Suspended',
                        'banned'    => 'Banned',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (User $record, Tables\Actions\DeleteAction $action) {
                        // Soft-deletion guard: don't allow deleting the last super admin
                        if ($record->hasRole('super_admin')) {
                            $remaining = User::role('super_admin')->where('id', '!=', $record->id)->count();
                            if ($remaining === 0) {
                                $action->cancel();
                                $action->failureNotificationTitle('Cannot delete the last super admin.');
                            }
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('users.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('users.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('users.delete') ?? false;
    }
}
