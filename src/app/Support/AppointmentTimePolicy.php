<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

class AppointmentTimePolicy
{
    private const STEP_MINUTES = 10;

    private const MIN_INTERVAL_MINUTES = 10;

    private const ALLOWED_PAST_MONTHS = 1;

    private const ALLOWED_FUTURE_MONTHS = 1;

    private const DEFAULT_WORKDAY_START = '09:00:00';

    private const DEFAULT_WORKDAY_END = '21:00:00';

    /**
     * Получить часовой пояс из настроек или дефолтный
     */
    public static function getTimezone(): string
    {
        $timezone = Setting::getSettings()?->timezone ?? config('app.timezone', 'UTC');
        return in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::getTimezone());
    }

    /**
     * Создать Carbon с учётом часового пояса
     */
    private static function createCarbon($dateTime = null): Carbon
    {
        $timezone = self::getTimezone();

        if ($dateTime === null) {
            return Carbon::now($timezone);
        }

        if ($dateTime instanceof Carbon) {
            return $dateTime->copy()->setTimezone($timezone);
        }

        return Carbon::parse($dateTime, $timezone);
    }

    public static function normalizeToStep($dateTime): ?Carbon
    {
        if (! $dateTime) {
            return null;
        }

        $value = self::createCarbon($dateTime);

        $roundedMinute = (int) (floor($value->minute / self::STEP_MINUTES) * self::STEP_MINUTES);

        return $value
            ->setMinute($roundedMinute)
            ->setSecond(0);
    }

    public static function workdayStartForDate($date): Carbon
    {
        $baseDate = self::createCarbon($date);

        return $baseDate
            ->copy()
            ->setTimeFromTimeString(self::workdayStartTime());
    }

    public static function workdayEndForDate($date): Carbon
    {
        $baseDate = self::createCarbon($date);

        return $baseDate
            ->copy()
            ->setTimeFromTimeString(self::workdayEndTime());
    }

    /**
     * Получить минимальную дату для записи (1 месяц назад)
     */
    public static function getMinDate(): Carbon
    {
        return self::createCarbon()->subMonthsNoOverflow(self::ALLOWED_PAST_MONTHS)->startOfDay();
    }

    /**
     * Получить максимальную дату для записи (1 месяц вперёд)
     */
    public static function getMaxDate(): Carbon
    {
        return self::createCarbon()->addMonthsNoOverflow(self::ALLOWED_FUTURE_MONTHS)->endOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayRange($date = null): array
    {
        $base = self::createCarbon($date);

        return [$base->copy()->startOfDay(), $base->copy()->endOfDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function todayRange(): array
    {
        return self::dayRange(self::now());
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function weekRange($date = null): array
    {
        $base = self::createCarbon($date);

        return [$base->copy()->startOfWeek(), $base->copy()->endOfWeek()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function monthRange($date = null): array
    {
        $base = self::createCarbon($date);

        return [$base->copy()->startOfMonth(), $base->copy()->endOfMonth()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function customRange(string $dateFrom, string $dateTo): array
    {
        $timezone = self::getTimezone();
        $start = Carbon::parse($dateFrom, $timezone)->startOfDay();
        $end = Carbon::parse($dateTo, $timezone)->endOfDay();

        return [$start, $end];
    }

    /**
     * Проверить, что дата в допустимом диапазоне
     */
    public static function isDateInRange($date): bool
    {
        $checkDate = self::createCarbon($date);

        return $checkDate->gte(self::getMinDate()) && $checkDate->lte(self::getMaxDate());
    }

    /**
     * Проверить, что время находится в пределах рабочего дня
     */
    public static function isWithinWorkday($dateTime): bool
    {
        if (! $dateTime) {
            return false;
        }

        $value = self::createCarbon($dateTime);
        $workdayStart = self::workdayStartForDate($value);
        $workdayEnd = self::workdayEndForDate($value);

        return $value->gte($workdayStart) && $value->lte($workdayEnd);
    }

    /**
     * Проверить, что оба времени находятся в рабочем окне дня
     */
    public static function isPairWithinWorkday($startAt, $endAt): bool
    {
        if (! $startAt || ! $endAt) {
            return false;
        }

        return self::createCarbon($startAt)->isSameDay(self::createCarbon($endAt))
            && self::isWithinWorkday($startAt) && self::isWithinWorkday($endAt);
    }

    /**
     * Проверить, что время не в прошлом
     */
    public static function isNotInPast($dateTime): bool
    {
        $checkDateTime = self::createCarbon($dateTime);
        $now = self::createCarbon();

        return $checkDateTime->gt($now);
    }

    /**
     * Проверить минимальную длительность записи (10 минут)
     */
    public static function isMinDurationValid($startAt, $endAt): bool
    {
        if (! $startAt || ! $endAt) {
            return true;
        }

        $start = self::createCarbon($startAt);
        $end = self::createCarbon($endAt);

        if ($end->lte($start)) {
            return false;
        }

        return $start->diffInMinutes($end) >= self::MIN_INTERVAL_MINUTES;
    }

    /**
     * Проверить, что время окончания позже времени начала
     */
    public static function isEndAfterStart($startAt, $endAt): bool
    {
        if (! $startAt || ! $endAt) {
            return true;
        }

        $start = self::createCarbon($startAt);
        $end = self::createCarbon($endAt);

        return $end->gt($start);
    }

    /**
     * Ограничить время начала с учётом длительности услуги
     * Последняя запись должна заканчиваться не позже конца рабочего дня
     */
    public static function clampStart($startAt, ?int $serviceDurationMinutes = null): ?Carbon
    {
        $start = self::normalizeToStep($startAt);

        if (! $start) {
            return null;
        }

        // Проверяем, что дата в допустимом диапазоне
        if (! self::isDateInRange($start)) {
            // Если дата в прошлом, возвращаем начало ближайшего рабочего дня
            if ($start->lt(self::getMinDate())) {
                return self::workdayStartForDate(self::getMinDate());
            }
            // Если дата слишком далеко в будущем, возвращаем последний допустимый день
            return self::workdayStartForDate(self::getMaxDate());
        }

        $workdayStart = self::workdayStartForDate($start);
        $workdayEnd = self::workdayEndForDate($start);

        // Учитываем длительность услуги при расчёте последнего возможного времени начала
        $duration = max($serviceDurationMinutes ?? self::MIN_INTERVAL_MINUTES, self::MIN_INTERVAL_MINUTES);
        $lastAllowedStart = $workdayEnd->copy()->subMinutes($duration);

        // Гарантируем минимум MIN_INTERVAL_MINUTES для записи
        if ($lastAllowedStart->lt($workdayStart)) {
            $lastAllowedStart = $workdayStart->copy();
        }

        if ($start->lt($workdayStart)) {
            return $workdayStart;
        }

        if ($start->gt($lastAllowedStart)) {
            return $lastAllowedStart;
        }

        return $start;
    }

    public static function suggestEndByService($startAt, ?int $serviceDurationMinutes, $fallbackEndAt = null): ?Carbon
    {
        // Передаём длительность услуги для корректного расчёта lastAllowedStart
        $start = self::clampStart($startAt, $serviceDurationMinutes);

        if (! $start) {
            return self::normalizeToStep($fallbackEndAt);
        }

        $duration = max((int) ($serviceDurationMinutes ?? 0), self::MIN_INTERVAL_MINUTES);
        $candidateEnd = $start->copy()->addMinutes($duration);

        return self::clampEndToWorkday($start, $candidateEnd);
    }

    public static function normalizeManualEndAt($startAt, $endAt, ?int $serviceDurationMinutes = null): ?Carbon
    {
        $start = self::clampStart($startAt, $serviceDurationMinutes);

        if (! $start) {
            return self::normalizeToStep($endAt);
        }

        $manualEnd = self::normalizeToStep($endAt);
        $minimumEnd = $start->copy()->addMinutes(self::MIN_INTERVAL_MINUTES);

        $candidateEnd = $manualEnd ?? $minimumEnd;

        if ($candidateEnd->lt($minimumEnd)) {
            $candidateEnd = $minimumEnd;
        }

        return self::clampEndToWorkday($start, $candidateEnd);
    }

    public static function resolveEndAt($startAt, ?int $serviceDurationMinutes, $endAt, bool $isDurationFixed = false): ?Carbon
    {
        if ($isDurationFixed && $serviceDurationMinutes) {
            return self::suggestEndByService($startAt, $serviceDurationMinutes, $endAt);
        }

        return self::normalizeManualEndAt($startAt, $endAt, null);
    }

    /**
     * Нормализовать start/end перед сохранением без зацикливания
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function normalizeForPersistence($startAt, $endAt, ?int $serviceDurationMinutes = null, bool $isDurationFixed = false): array
    {
        $durationForStartClamp = $isDurationFixed ? $serviceDurationMinutes : null;
        $start = self::clampStart($startAt, $durationForStartClamp);

        if (! $start) {
            return [null, null];
        }

        $end = self::resolveEndAt($start, $serviceDurationMinutes, $endAt, $isDurationFixed);

        if (! $end) {
            $end = $start->copy()->addMinutes(self::MIN_INTERVAL_MINUTES);
        }

        if (! $end->gt($start)) {
            $end = $start->copy()->addMinutes(self::MIN_INTERVAL_MINUTES);
        }

        $end = self::clampEndToWorkday($start, $end);

        return [$start, $end];
    }

    /**
     * Полный набор проверок на сервере
     *
     * @return array<string, string>
     */
    public static function validatePair($startAt, $endAt, ?int $excludeId = null, bool $checkPast = false, bool $checkOverlap = true): array
    {
        $errors = [];

        if (! $startAt || ! $endAt) {
            return [
                'start_at' => 'Укажите время начала записи.',
                'end_at' => 'Укажите время окончания записи.',
            ];
        }

        if (! self::isDateInRange($startAt) || ! self::isDateInRange($endAt)) {
            $errors['start_at'] = 'Запись можно создавать только в пределах 1 месяца от текущей даты.';
        }

        if ($checkPast && ! self::isNotInPast($startAt)) {
            $errors['start_at'] = 'Нельзя создать запись на прошедшее время.';
        }

        if (! self::isPairWithinWorkday($startAt, $endAt)) {
            $errors['start_at'] = 'Время записи должно быть в пределах рабочего дня.';
        }

        if (! self::isEndAfterStart($startAt, $endAt)) {
            $errors['end_at'] = 'Время окончания должно быть позже времени начала.';
            return $errors;
        }

        if (! self::isMinDurationValid($startAt, $endAt)) {
            $errors['end_at'] = 'Минимальная длительность записи — 10 минут.';
            return $errors;
        }

        if ($checkOverlap && self::hasOverlap(self::createCarbon($startAt), self::createCarbon($endAt), $excludeId)) {
            $errors['end_at'] = 'Это время уже занято другой записью.';
        }

        return $errors;
    }

    private static function clampEndToWorkday(Carbon $start, Carbon $candidateEnd): Carbon
    {
        $workdayStart = self::workdayStartForDate($start);
        $workdayEnd = self::workdayEndForDate($start);

        // Проверяем, что конец не раньше начала записи
        if ($candidateEnd->lte($start)) {
            $candidateEnd = $start->copy()->addMinutes(self::MIN_INTERVAL_MINUTES);
        }

        // Проверяем, что конец не раньше начала рабочего дня
        if ($candidateEnd->lt($workdayStart)) {
            $candidateEnd = $workdayStart->copy()->addMinutes(self::MIN_INTERVAL_MINUTES);
        }

        // Проверяем, что конец не позже конца рабочего дня
        if ($candidateEnd->gt($workdayEnd)) {
            $candidateEnd = $workdayEnd;
        }

        return $candidateEnd;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function normalizePair($startAt, $endAt): array
    {
        $start = self::clampStart($startAt);

        if (! $start) {
            return [null, self::normalizeToStep($endAt)];
        }

        $end = self::normalizeManualEndAt($start, $endAt);

        return [$start, $end];
    }

    private static function workdayStartTime(): string
    {
        return Setting::getSettings()?->workday_start?->format('H:i:s')
            ?? self::DEFAULT_WORKDAY_START;
    }

    private static function workdayEndTime(): string
    {
        return Setting::getSettings()?->workday_end?->format('H:i:s')
            ?? self::DEFAULT_WORKDAY_END;
    }

    /**
     * Проверить пересечение с существующими записями
     * @param Carbon $startAt Время начала
     * @param Carbon $endAt Время окончания
     * @param int|null $excludeId ID записи для исключения (при редактировании)
     * @return bool true если есть пересечение
     */
    public static function hasOverlap(Carbon $startAt, Carbon $endAt, ?int $excludeId = null): bool
    {
        return \App\Models\Appointment::query()
            ->where('status', '!=', 'cancelled')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->exists();
    }

    /**
     * Получить доступные слоты времени на указанную дату
     * @param Carbon|string $date Дата
     * @param int $durationMinutes Длительность услуги
     * @return array Массив доступных слотов [start_time => Carbon]
     */
    public static function getAvailableSlots($date, int $durationMinutes = self::MIN_INTERVAL_MINUTES): array
    {
        $date = self::createCarbon($date)->startOfDay();
        $workdayStart = self::workdayStartForDate($date);
        $workdayEnd = self::workdayEndForDate($date);

        $slots = [];
        $currentSlot = $workdayStart->copy();

        // Последнее возможное время начала с учётом длительности
        $lastAllowedStart = $workdayEnd->copy()->subMinutes($durationMinutes);

        while ($currentSlot->lte($lastAllowedStart)) {
            $slotEnd = $currentSlot->copy()->addMinutes($durationMinutes);

            if (! self::hasOverlap($currentSlot, $slotEnd)) {
                $slots[$currentSlot->format('H:i')] = $currentSlot->copy();
            }

            $currentSlot->addMinutes(self::STEP_MINUTES);
        }

        return $slots;
    }
}
