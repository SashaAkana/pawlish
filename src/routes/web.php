<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FinancialReportExportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Здесь только необходимый минимум для работы системы.
| Filament автоматически регистрирует свои маршруты через AdminPanelProvider.
|
*/

Route::middleware('auth')->get('/panel/financial-report/download', [FinancialReportExportController::class, 'download'])
    ->name('financial-report.download');

Route::get('/', function () {
    return redirect('/panel');
})->name('home');
