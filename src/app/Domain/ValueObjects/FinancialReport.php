<?php

namespace App\Domain\ValueObjects;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\Setting;
use App\Support\AppointmentTimePolicy;
use Carbon\Carbon;

class FinancialReport
{
    public function __construct(
        public readonly float $revenue,
        public readonly float $expenses,
        public readonly float $tax,
        public readonly float $netProfit,
        public readonly float $netAfterTax,
        public readonly float $averageCheck,
        public readonly int $completedOrders
    ) {}

    public static function forPeriod(Carbon $startDate, Carbon $endDate): self
    {
        $settings = Setting::getSettings();
        
        $revenue = Appointment::where('status', 'completed')
            ->where('is_paid', true)
            ->whereBetween('start_at', [$startDate, $endDate])
            ->sum('price_at_moment');
            
        $completedOrders = Appointment::where('status', 'completed')
            ->where('is_paid', true)
            ->whereBetween('start_at', [$startDate, $endDate])
            ->count();
            
        $expenses = Expense::whereDate('expense_date', '>=', $startDate->toDateString())
            ->whereDate('expense_date', '<=', $endDate->toDateString())
            ->sum('amount');
            
        $tax = $revenue * ($settings->tax_percent / 100);
        $netProfit = $revenue - $expenses;
        $netAfterTax = $netProfit - $tax;
        $averageCheck = $completedOrders > 0 ? $revenue / $completedOrders : 0;
        
        return new self(
            revenue: $revenue,
            expenses: $expenses,
            tax: $tax,
            netProfit: $netProfit,
            netAfterTax: $netAfterTax,
            averageCheck: $averageCheck,
            completedOrders: $completedOrders
        );
    }

    public static function today(): self
    {
        [$startDate, $endDate] = AppointmentTimePolicy::todayRange();

        return self::forPeriod($startDate, $endDate);
    }

    public static function thisWeek(): self
    {
        [$startDate, $endDate] = AppointmentTimePolicy::weekRange();

        return self::forPeriod($startDate, $endDate);
    }

    public static function thisMonth(): self
    {
        [$startDate, $endDate] = AppointmentTimePolicy::monthRange();

        return self::forPeriod($startDate, $endDate);
    }
}
