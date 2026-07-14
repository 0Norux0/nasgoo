<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Role')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->disabled(fn ($record) => $record && in_array($record->name, ['super_admin', 'admin_staff', 'vendor', 'customer'], true))
                        ->helperText('System roles (super_admin/admin_staff/vendor/customer) cannot be renamed.'),
                    Forms\Components\TextInput::make('guard_name')
                        ->default('web')
                        ->required()
                        ->disabled(),
                ]),

            Forms\Components\Section::make('Permissions')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->relationship('permissions', 'name')
                        ->columns(3)
                        ->bulkToggleable()
                        ->searchable()
                        ->helperText('Permissions are grouped by module prefix (e.g. users.*, orders.*).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin_staff' => 'warning',
                        'vendor'      => 'info',
                        'customer'    => 'gray',
                        default       => 'primary',
                    }),
                Tables\Columns\TextColumn::make('guard_name')->toggleable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => ! in_array($record->name, ['super_admin', 'admin_staff', 'vendor', 'customer'], true)),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('roles.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('roles.manage') ?? false)
            && ! in_array($record->name, ['super_admin', 'admin_staff', 'vendor', 'customer'], true);
    }
}
