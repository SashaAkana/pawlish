<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Closure;
use App\Models\Service;
use App\Models\Setting;
use App\Support\AppointmentTimePolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';
    protected static ?string $title = 'История записей';
    protected static ?string $label = 'запись';
    protected static ?string $pluralLabel = 'записи';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        'scheduled' => 'Запланировано',
                        'completed' => 'Выполнено',
                        'cancelled' => 'Отменено',
                    ])
                    ->default('scheduled')
                    ->required(),
                Forms\Components\Toggle::make('is_paid')
                    ->label('Оплачено')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('start_at')
                    ->label('Дата и время')
                    ->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Услуга'),
                Tables\Columns\TextColumn::make('price_at_moment')
                    ->label('Цена')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ' ') . ' ' . (\App\Models\Setting::getSettings()?->currency ?? '₽')),
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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
