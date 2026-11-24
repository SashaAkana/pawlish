<?php

use Illuminate\Support\Facades\Schedule;
use App\Models\Appointment;
use App\Models\Setting;
use App\Jobs\SendMasterReminderJob;
use App\Console\Commands\CreateRecurringExpenses;

Schedule::call(function () {
    $settings = Setting::getSettings();

    if ($settings?->timezone) {
        config(['app.timezone' => $settings->timezone]);
        date_default_timezone_set($settings->timezone);
    }
    
    if (!$settings?->telegram_bot_token || !$settings?->master_chat_id) {
        return;
    }

    $appointments = Appointment::with(['client', 'service'])
        ->where('status', 'scheduled')
        ->where('reminder_sent', false)
        ->where('start_at', '<=', now()->addMinutes($settings->reminder_minutes_before))
        ->where('start_at', '>', now())
        ->get();

    foreach ($appointments as $appointment) {
        SendMasterReminderJob::dispatch($appointment->id);
    }
})->everyMinute()->name('send-appointment-reminders')->withoutOverlapping();

Schedule::command('expenses:create-recurring')
    ->hourly()
    ->withoutOverlapping()
    ->name('create-recurring-expenses');
