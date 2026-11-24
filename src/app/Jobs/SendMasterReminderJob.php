<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Setting;
use App\Support\AppointmentTimePolicy;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendMasterReminderJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 900;
    public int $tries = 3;
    public array $backoff = [30, 60];

    public function __construct(public int $appointmentId) {}

    public function uniqueId(): string
    {
        return (string) $this->appointmentId;
    }

    public function handle(): void
    {
        $settings = Setting::getSettings();
        $appointment = Appointment::with(['client', 'service'])->find($this->appointmentId);
        $now = AppointmentTimePolicy::now();
        if (! $settings?->telegram_bot_token || ! $settings?->master_chat_id
            || ! $appointment || $appointment->status !== 'scheduled' || $appointment->reminder_sent
            || ! $appointment->client || ! $appointment->service
            || $appointment->start_at->lte($now)
            || $appointment->start_at->gt($now->copy()->addMinutes($settings->reminder_minutes_before))) {
            return;
        }

        $message = strtr($settings->reminder_template, [
            '{client_name}' => $appointment->client->name,
            '{service}' => $appointment->service->name,
            '{date}' => $appointment->start_at->format('d.m.Y'),
            '{time}' => $appointment->start_at->format('H:i'),
            '{price}' => $appointment->price_at_moment,
            '{currency}' => $settings->currency,
        ]);

        try {
            $response = Http::connectTimeout(5)->timeout(10)->post(
                "https://api.telegram.org/bot{$settings->telegram_bot_token}/sendMessage",
                ['chat_id' => $settings->master_chat_id, 'text' => $message]
            );
            if (! $response->successful() || $response->json('ok') !== true) {
                throw new \RuntimeException('Telegram rejected reminder');
            }
        } catch (\Throwable) {
            // No URL, token, client data or raw response in failed-job logs.
            throw new \RuntimeException('Telegram reminder delivery failed.');
        }

        Appointment::query()->whereKey($appointment->id)
            ->where('start_at', $appointment->getRawOriginal('start_at'))
            ->where('end_at', $appointment->getRawOriginal('end_at'))
            ->where('client_id', $appointment->client_id)->where('service_id', $appointment->service_id)
            ->where('status', 'scheduled')->update(['reminder_sent' => true]);
    }
}
