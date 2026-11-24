<?php

namespace App\Filament\Widgets;

use Closure;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Setting;
use App\Support\AppointmentTimePolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\Widgets\CalendarWidget;

class AppointmentCalendarWidget extends CalendarWidget
{
    protected static bool $isDiscovered = false;

    protected string $calendarView = 'timeGridWeek';

    public ?int $calendarDurationDays = null;

    public function getHeading(): string | HtmlString
    {
        return 'Календарь';
    }

    public function getOptions(): array
    {
        $durationDays = $this->getCalendarDurationDays();

        return [
            'view' => 'timeGridWeek',
            'duration' => ['days' => $durationDays],
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => '',
            ],
            'slotMinTime' => Setting::getSettings()?->workday_start?->format('H:i:s') ?? '09:00:00',
            'slotMaxTime' => Setting::getSettings()?->workday_end?->format('H:i:s') ?? '21:00:00',
            'allDaySlot' => false,
            'slotDuration' => '00:10:00',
            'snapDuration' => '00:10:00',
            'locale' => 'ru',
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('calendar2days')
                ->label('2 дня')
                ->color(fn (): string => $this->getCalendarDurationDays() === 2 ? 'primary' : 'gray')
                ->action(fn (): null => $this->setCalendarDuration(2)),
            Action::make('calendar3days')
                ->label('3 дня')
                ->color(fn (): string => $this->getCalendarDurationDays() === 3 ? 'primary' : 'gray')
                ->action(fn (): null => $this->setCalendarDuration(3)),
            Action::make('calendar7days')
                ->label('7 дней')
                ->color(fn (): string => $this->getCalendarDurationDays() === 7 ? 'primary' : 'gray')
                ->action(fn (): null => $this->setCalendarDuration(7)),
        ];
    }

    private function setCalendarDuration(int $days): null
    {
        $this->calendarDurationDays = in_array($days, [2, 3, 7], true) ? $days : 7;

        $this->setOption('view', 'timeGridWeek');
        $this->setOption('duration', ['days' => $this->calendarDurationDays]);

        return null;
    }

    private function getCalendarDurationDays(): int
    {
        return $this->calendarDurationDays ??= $this->resolveDefaultCalendarDuration();
    }

    private function resolveDefaultCalendarDuration(): int
    {
        $userAgent = (string) request()->userAgent();
        $mobileMarkers = [
            'Android',
            'webOS',
            'iPhone',
            'iPad',
            'iPod',
            'BlackBerry',
            'IEMobile',
            'Opera Mini',
            'Mobile',
        ];

        return Str::contains($userAgent, $mobileMarkers, true) ? 2 : 7;
    }

    public function getEvents(array $fetchInfo = []): array
    {
        $timezone = AppointmentTimePolicy::getTimezone();

        $rangeStart = isset($fetchInfo['start'])
            ? Carbon::parse(Arr::get($fetchInfo, 'startStr', $fetchInfo['start']), $timezone)
            : now($timezone)->startOfDay();

        $rangeEnd = isset($fetchInfo['end'])
            ? Carbon::parse(Arr::get($fetchInfo, 'endStr', $fetchInfo['end']), $timezone)
            : now($timezone)->startOfDay()->addDays($this->getCalendarDurationDays());

        return Appointment::with(['client', 'service'])
            ->where('start_at', '<', $rangeEnd)
            ->where('end_at', '>', $rangeStart)
            ->orderBy('start_at')
            ->get()
            ->map(
                fn (Appointment $appointment): array => CalendarEvent::make($appointment)
                    ->title(
                        $appointment->client->name . ' - ' . $appointment->service->name
                        . ($appointment->comment ? (' | ' . $appointment->comment) : '')
                    )
                    ->start($appointment->start_at)
                    ->end($appointment->end_at)
                    ->backgroundColor(
                        match ($appointment->status) {
                            'scheduled' => 'rgba(var(--primary-600), 0.7)',
                            'completed' => 'rgba(var(--success-600), 0.7)',
                            'cancelled' => 'rgba(var(--gray-500), 0.7)',
                            default => 'rgba(var(--gray-500), 0.7)',
                        }
                    )
                    ->extendedProps([
                        'status' => $appointment->status,
                        'price' => $appointment->price_at_moment,
                        'is_paid' => $appointment->is_paid,
                    ])
                    ->toArray()
            )
            ->map(function (array $event) use ($timezone): array {
                $event['start'] = Carbon::parse($event['start'])->setTimezone($timezone)->format('Y-m-d\TH:i:s');
                $event['end'] = Carbon::parse($event['end'])->setTimezone($timezone)->format('Y-m-d\TH:i:s');

                return $event;
            })
            ->toArray();
    }

    public function getSchema(?string $model = null): ?array
    {
        return [
            Section::make('Информация о записи')
                ->schema([
                    Select::make('client_id')
                        ->label('Клиент')
                        ->relationship('client', 'name', modifyQueryUsing: fn ($query, ?Model $record = null) => $query->where(fn ($q) => $q->whereNull('clients.deleted_at')->when($record?->client_id, fn ($q) => $q->orWhere('clients.id', $record->client_id))))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Имя')
                                ->required(),
                            TextInput::make('phone')
                                ->label('Телефон'),
                        ]),
                    Select::make('service_id')
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
                    DateTimePicker::make('start_at')
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
                    DateTimePicker::make('end_at')
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
                ])->columns(2),
            Section::make('Финансы и статус')
                ->schema([
                    TextInput::make('price_at_moment')
                        ->label('Цена')
                        ->required()
                        ->numeric()
                            ->minValue(0)
                        ->prefix(Setting::getSettings()->currency ?? '₽'),
                    Select::make('payment_type')
                        ->label('Способ оплаты')
                        ->options([
                            'cash' => 'Наличные',
                                'card' => 'Карта',
                            'transfer' => 'Перевод',
                        ])
                        ->rules(['nullable', 'in:cash,card,transfer']),
                    TextInput::make('comment')
                        ->label('Комментарий')
                        ->maxLength(50)
                        ->rules(['nullable', 'string', 'max:50']),
                    Toggle::make('is_paid')
                        ->label('Оплачено')
                        ->default(false),
                    Select::make('status')
                        ->label('Статус')
                        ->options([
                            'scheduled' => 'Запланировано',
                            'completed' => 'Выполнено',
                            'cancelled' => 'Отменено',
                        ])
                        ->default('scheduled')
                        ->required(),
                ])->columns(2),
        ];
    }
}
