<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardOverviewWidget;
use App\Filament\Widgets\TodayAppointmentsListWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            DashboardOverviewWidget::class,
            TodayAppointmentsListWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
