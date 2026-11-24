<?php

namespace App\Models;

use App\Support\AppointmentTimePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Appointment extends Model
{
    protected $fillable = [
        'client_id',
        'service_id',
        'start_at',
        'end_at',
        'comment',
        'price_at_moment',
        'payment_type',
        'status',
        'is_paid',
        'reminder_sent'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_paid' => 'boolean',
        'reminder_sent' => 'boolean',
        'price_at_moment' => 'decimal:2'
    ];

    protected static function booted()
    {
        static::saving(function (self $appointment): void {
            $isCreating = ! $appointment->exists;
            if (($isCreating || $appointment->isDirty('service_id'))
                && ! Service::query()->whereKey($appointment->service_id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['service_id' => 'Выберите активную услугу.']);
            }
            if (($isCreating || $appointment->isDirty('client_id'))
                && ! Client::query()->whereKey($appointment->client_id)->exists()) {
                throw ValidationException::withMessages(['client_id' => 'Выберите действующего клиента.']);
            }
            $timeFieldsWereChanged = $appointment->isDirty(['start_at', 'end_at', 'service_id']);

            if ($appointment->isDirty(['start_at', 'end_at', 'service_id', 'client_id', 'status'])) {
                $appointment->reminder_sent = false;
            }

            if (! $isCreating && ! $timeFieldsWereChanged) {
                if ($appointment->isDirty('status') && $appointment->status !== 'cancelled'
                    && AppointmentTimePolicy::hasOverlap($appointment->start_at, $appointment->end_at, $appointment->id)) {
                    throw ValidationException::withMessages(['status' => 'Это время уже занято другой записью.']);
                }
                return;
            }

            $serviceDuration = null;
            $isDurationFixed = false;
            if ($appointment->service_id) {
                $service = Service::query()
                    ->select(['duration_minutes', 'is_duration_fixed'])
                    ->whereKey($appointment->service_id)
                    ->first();

                $serviceDuration = (int) ($service?->duration_minutes ?? 0);
                $isDurationFixed = (bool) ($service?->is_duration_fixed ?? false);

                if ($serviceDuration <= 0) {
                    $serviceDuration = null;
                }
            }

            [$normalizedStart, $normalizedEnd] = AppointmentTimePolicy::normalizeForPersistence(
                $appointment->start_at,
                $appointment->end_at,
                $serviceDuration,
                $isDurationFixed,
            );

            if (! $normalizedStart || ! $normalizedEnd) {
                throw ValidationException::withMessages([
                    'start_at' => 'Укажите корректное время начала записи.',
                    'end_at' => 'Укажите корректное время окончания записи.',
                ]);
            }

            if ($isDurationFixed && $serviceDuration !== null
                && abs($normalizedStart->diffInMinutes($normalizedEnd) - $serviceDuration) > 0.001) {
                throw ValidationException::withMessages(['end_at' => 'Услуга не помещается в рабочий день.']);
            }

            $appointment->start_at = $normalizedStart;
            $appointment->end_at = $normalizedEnd;

            $errors = AppointmentTimePolicy::validatePair(
                $appointment->start_at,
                $appointment->end_at,
                $appointment->exists ? $appointment->id : null,
                false,
                $appointment->status !== 'cancelled',
            );

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
