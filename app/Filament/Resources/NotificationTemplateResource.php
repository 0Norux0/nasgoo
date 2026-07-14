<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationTemplateResource\Pages;
use App\Models\NotificationTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'event_key';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Template')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('event_key')
                        ->options(array_combine(
                            NotificationTemplate::supportedEventKeys(),
                            NotificationTemplate::supportedEventKeys(),
                        ))
                        ->required()
                        ->searchable(),
                    Forms\Components\Select::make('channel')
                        ->options(array_combine(
                            NotificationTemplate::supportedChannels(),
                            NotificationTemplate::supportedChannels(),
                        ))
                        ->required(),
                    Forms\Components\Select::make('locale')
                        ->options(['en' => 'English', 'ar' => 'Arabic', 'ur' => 'Urdu'])
                        ->required()
                        ->default('en'),
                ]),

            Forms\Components\Section::make('Content')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->helperText('For mail channel. Supports {{ placeholder }} syntax.'),
                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->rows(8)
                        ->helperText('Use {{ placeholder }} for variables.'),
                    Forms\Components\TagsInput::make('placeholders')
                        ->helperText('List of placeholder names supported by this template.'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_key')->searchable()->sortable()->badge(),
                Tables\Columns\TextColumn::make('channel')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('locale')->badge(),
                Tables\Columns\TextColumn::make('subject')->limit(40)->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_key')
                    ->options(array_combine(
                        NotificationTemplate::supportedEventKeys(),
                        NotificationTemplate::supportedEventKeys(),
                    )),
                Tables\Filters\SelectFilter::make('channel')
                    ->options(array_combine(
                        NotificationTemplate::supportedChannels(),
                        NotificationTemplate::supportedChannels(),
                    )),
                Tables\Filters\SelectFilter::make('locale')
                    ->options(['en' => 'English', 'ar' => 'Arabic', 'ur' => 'Urdu']),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('event_key');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNotificationTemplates::route('/'),
            'create' => Pages\CreateNotificationTemplate::route('/create'),
            'edit'   => Pages\EditNotificationTemplate::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }
}
