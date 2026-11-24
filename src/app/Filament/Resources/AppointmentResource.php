<?php

namespace App\Filament\Resources;

use Closure;
use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Setting;
use App\Support\AppointmentTimePolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Записи и финансы';
    protected static ?string $modelLabel = 'Запись';
    protected static ?string $pluralModelLabel = 'Записи';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о записи')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Клиент')
                            ->relationship('client', 'name', modifyQueryUsing: fn ($query, ?Model $record = null) => $query->where(fn ($q) => $q->whereNull('clients.deleted_at')->when($record?->client_id, fn ($q) => $q->orWhere('clients.id', $record->client_id))))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Имя')
                                    ->required(),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Телефон'),
                            ]),
                        Forms\Components\Select::make('service_id')
                            ->label('Услуга')
                            ->relationship('service', 'name', modifyQueryUsing: fn ($query, ?Model $record = null) => $query->where(fn ($q) => $q->where('is_active', true)->when($record?->service_id, fn ($q) => $q->orWhere('services.id', $record->service_id))))
                            ->getOptionLabelFromRecordUsing(fn (Model $record): string =>
                                $record->name . (($record->is_duration_fixed ?? false) ? ' 🔒' : '')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    $service = Service::find($state);
                                    if ($service) {
                                        $set('price_at_moment', $service->base_price);

                                        if ($service->is_duration_fixed) {
                                            $start = $get('start_at') ?: AppointmentTimePolicy::workdayStartForDate(now());
                                            $set(
                                                'end_at',
                                                AppointmentTimePolicy::suggestEndByService(
                                                    $start,
                                                    (int) $service->duration_minutes,
                                                    $get('end_at')
                                                )
                                            );
                                        }
                                    }
                                }
                            }),
                        Forms\Components\DateTimePicker::make('start_at')
                            ->label('Начало')
                            ->required()
                            ->default(fn () => AppointmentTimePolicy::workdayStartForDate(now()))
                            ->native(false)
                            ->step(600)
                            ->seconds(false)
                            ->minutesStep(10)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $serviceId = $get('service_id');
                                if (! $serviceId) {
                                    return;
                                }

                                $service = Service::find($serviceId);

                                if (! $service?->is_duration_fixed) {
                                    return;
                                }

                                $set(
                                    'end_at',
                                    AppointmentTimePolicy::suggestEndByService(
                                        $state,
                                        (int) $service->duration_minutes,
                                        $get('end_at')
                                    )
                                );
                            })
                            ->minDate(fn () => AppointmentTimePolicy::getMinDate())
                            ->maxDate(fn () => AppointmentTimePolicy::getMaxDate())
                            ->timezone(fn () => AppointmentTimePolicy::getTimezone())
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        if (! $value) {
                                            return;
                                        }

                                        if (! AppointmentTimePolicy::isDateInRange($value)) {
                                            $fail('Запись можно создавать только в пределах 1 месяца от текущей даты.');
                                            return;
                                        }

                                        if (! AppointmentTimePolicy::isWithinWorkday($value)) {
                                            $fail('Время записи должно быть в пределах рабочего дня.');
                                        }
                                    };
                                },
                            ]),
                        Forms\Components\DateTimePicker::make('end_at')
                            ->label('Окончание')
                            ->required()
                            ->default(fn () => AppointmentTimePolicy::workdayEndForDate(now()))
                            ->helperText(fn (callable $get): string =>
                                Service::query()->whereKey($get('service_id'))->value('is_duration_fixed')
                                    ? 'Для этой услуги окончание фиксируется автоматически по длительности.'
                                    : 'Окончание можно настроить вручную в рамках рабочего дня.'
                            )
                            ->disabled(fn (callable $get): bool =>
                                (bool) Service::query()->whereKey($get('service_id'))->value('is_duration_fixed')
                            )
                            ->native(false)
                            ->step(600)
                            ->seconds(false)
                            ->minutesStep(10)
                            ->minDate(fn () => AppointmentTimePolicy::getMinDate())
                            ->maxDate(fn () => AppointmentTimePolicy::getMaxDate())
                            ->timezone(fn () => AppointmentTimePolicy::getTimezone())
                            ->rules([
                                fn (callable $get, ?\Illuminate\Database\Eloquent\Model $record = null) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                    $start = $get('start_at');
                                    if (! $start || ! $value) {
                                        return;
                                    }

                                    $excludeId = $record?->getKey();
                                    $errors = AppointmentTimePolicy::validatePair($start, $value, $excludeId, false, $get('status') !== 'cancelled');

                                    foreach ($errors as $message) {
                                        $fail($message);
                                    }
                                },
                            ]),
                        Forms\Components\TextInput::make('comment')
                            ->label('Комментарий')
                            ->maxLength(50)
                            ->rules(['nullable', 'string', 'max:50']),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Финансы и статус')
                    ->schema([
                        Forms\Components\TextInput::make('price_at_moment')
                            ->label('Цена')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix(Setting::getSettings()->currency ?? '₽'),
                        Forms\Components\Select::make('payment_type')
                            ->label('Способ оплаты')
                            ->options([
                                'cash' => 'Наличные',
                                'card' => 'Карта',
                                'transfer' => 'Перевод',
                            ])
                            ->rules(['nullable', 'in:cash,card,transfer']),
                        Forms\Components\Toggle::make('is_paid')
                            ->label('Оплачено')
                            ->default(false),
                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                'scheduled' => 'Запланировано',
                                'completed' => 'Выполнено',
                                'cancelled' => 'Отменено',
                            ])
                            ->default('scheduled')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('start_at')
                    ->label('Дата и время')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Клиент')
                    ->searchable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Услуга'),
                Tables\Columns\TextColumn::make('price_at_moment')
                    ->label('Цена')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ' ') . ' ' . (\App\Models\Setting::getSettings()?->currency ?? '₽'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Оплачено')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'scheduled' => 'Запланировано',
                        'completed' => 'Выполнено',
                        'cancelled' => 'Отменено',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Запланировано',
                        'completed' => 'Выполнено',
                        'cancelled' => 'Отменено',
                    ]),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('Дата'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['date'],
                                fn ($query, $date) => $query->whereDate('start_at', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
