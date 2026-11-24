<?php

namespace App\Filament\Widgets;

use App\Domain\ValueObjects\FinancialReport;
use App\Models\Appointment;
use App\Models\Setting;
use App\Support\AppointmentTimePolicy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $todayReport = FinancialReport::today();
        $monthReport = FinancialReport::thisMonth();
        $settings = Setting::getSettings();
        [$todayStart, $todayEnd] = AppointmentTimePolicy::todayRange();

        $todayScheduled = Appointment::query()
            ->whereBetween('start_at', [$todayStart, $todayEnd])
            ->where('status', 'scheduled')
            ->count();

        $todayCompleted = Appointment::query()
            ->whereBetween('start_at', [$todayStart, $todayEnd])
            ->where('status', 'completed')
            ->count();

        $currency = $settings->currency ?? '₽';

        return [
            Stat::make('Доход сегодня', number_format($todayReport->revenue, 0) . ' ' . $currency)
                ->description('Выполненные и оплаченные записи')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Записи сегодня', (string) $todayScheduled)
                ->description($todayCompleted . ' выполнено')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Доход за месяц', number_format($monthReport->revenue, 0) . ' ' . $currency)
                ->description($monthReport->completedOrders . ' выполненных записей')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),

            Stat::make('Расходы за месяц', number_format($monthReport->expenses, 0) . ' ' . $currency)
                ->description('Включая аренду и материалы')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('danger'),

            Stat::make('Чистая прибыль', number_format($monthReport->netProfit, 0) . ' ' . $currency)
                ->description('Доход минус расходы')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Налог к оплате', number_format($monthReport->tax, 0) . ' ' . $currency)
                ->description(($settings->tax_percent ?? 0) . '% от дохода')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
        ];
    }
}
