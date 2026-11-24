<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $database = config('database.connections.sqlite.database');
        $databaseExists = config('database.default') !== 'sqlite' || $database === ':memory:' || is_file((string) $database);
        if ($databaseExists && Schema::hasTable('settings')) {
            $timezone = Setting::query()->value('timezone') ?: config('app.timezone', 'UTC');

            if (! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                $timezone = 'UTC';
            }
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
