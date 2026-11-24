<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use DateTimeZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?string $modelLabel = 'Настройки';
    protected static ?string $pluralModelLabel = 'Настройки';
    
    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Настройки')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Финансы')
                            ->schema([
                                Forms\Components\TextInput::make('tax_percent')
                                    ->label('Налог (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required(),
                                Forms\Components\TextInput::make('currency')
                                    ->label('Валюта')
                                    ->maxLength(10)
                                    ->default('₽')
                                    ->required(),
                                Forms\Components\TimePicker::make('workday_start')
                                    ->label('Начало рабочего дня')
                                    ->step(600)
                                    ->seconds(false)
                                    ->minutesStep(10)
                                    ->dehydrateStateUsing(fn ($state) => static::normalizeTimeToTenMinutes($state))
                                    ->required(),
                                Forms\Components\TimePicker::make('workday_end')
                                    ->label('Конец рабочего дня')
                                    ->step(600)
                                    ->seconds(false)
                                    ->minutesStep(10)
                                    ->dehydrateStateUsing(fn ($state) => static::normalizeTimeToTenMinutes($state))
                                    ->required(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Telegram')
                            ->schema([
                                Forms\Components\TextInput::make('telegram_bot_token')
                                    ->label('Telegram Bot Token')
                                    ->helperText('Получите у @BotFather. Оставьте *** для сохранения текущего токена.')
                                    ->rules(['nullable', 'regex:/^(?:\*\*\*|[0-9]+:[A-Za-z0-9_-]+)$/'])
                                    ->password()
                                    ->revealable(),
                                Forms\Components\TextInput::make('master_chat_id')
                                    ->label('Ваш Chat ID')
                                    ->helperText('Укажите числовой ID своего личного чата. Автоматическая привязка отключена.')
                                    ->rules(['nullable', 'regex:/^[1-9][0-9]{0,15}$/']),
                                Forms\Components\TextInput::make('reminder_minutes_before')
                                    ->label('Напоминать за (минут)')
                                    ->numeric()
                                    ->minValue(5)
                                    ->maxValue(1440)
                                    ->default(60),
                                Forms\Components\Textarea::make('reminder_template')
                                    ->label('Шаблон напоминания')
                                    ->helperText('Доступные переменные: {client_name}, {service}, {date}, {time}, {price}, {currency}')
                                    ->rows(3)
                                    ->default('Напоминание: {client_name}, {service} в {date} {time}, {price} {currency}'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Системное')
                            ->schema([
                                Forms\Components\Select::make('timezone')
                                    ->label('Часовой пояс')
                                    ->options(array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                                    ->default('UTC')
                                    ->searchable()
                                    ->required()
                                    ->helperText('Используется для расчёта времени напоминаний и отображения дат/времени в системе.'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tax_percent')
                    ->label('Налог')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Валюта'),
                Tables\Columns\TextColumn::make('timezone')
                    ->label('Часовой пояс'),
                Tables\Columns\TextColumn::make('workday_start')
                    ->label('Рабочий день')
                    ->formatStateUsing(fn ($record) => $record->workday_start->format('H:i') . ' - ' . $record->workday_end->format('H:i')),
                Tables\Columns\IconColumn::make('telegram_bot_token')
                    ->label('Telegram')
                    ->boolean(fn ($state) => !empty($state)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }

    private static function normalizeTimeToTenMinutes($state): ?string
    {
        if (! $state) {
            return null;
        }

        $time = Carbon::parse($state);
        $roundedMinute = (int) (floor($time->minute / 10) * 10);

        return $time
            ->setMinute($roundedMinute)
            ->setSecond(0)
            ->format('H:i:s');
    }
}
