<?php

namespace App\Http\Controllers;

use App\Domain\ValueObjects\FinancialReport as FinancialReportVO;
use App\Models\Appointment;
use App\Models\Expense;
use App\Models\Setting;
use App\Support\AppointmentTimePolicy;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class FinancialReportExportController extends Controller
{
    public function download(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'in:today,week,month,custom'],
            'format' => ['required', 'in:csv,pdf'],
            'dateFrom' => ['nullable', 'required_if:period,custom', 'date'],
            'dateTo' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:dateFrom'],
        ]);

        [$startDate, $endDate, $periodLabel] = $this->resolvePeriod(
            $data['period'],
            $data['dateFrom'] ?? null,
            $data['dateTo'] ?? null,
        );

        $settings = Setting::getSettings();
        $currency = $settings?->currency ?? '₽';
        abort_if($startDate->diffInDays($endDate) >= 366, 422, 'Период экспорта — не более 366 дней.');

        $report = FinancialReportVO::forPeriod($startDate, $endDate);

        $appointments = Appointment::with(['client', 'service'])
            ->where('status', 'completed')
            ->where('is_paid', true)
            ->whereBetween('start_at', [$startDate, $endDate])
            ->orderBy('start_at')
            ->get();

        $expenses = Expense::query()
            ->whereDate('expense_date', '>=', $startDate->toDateString())
            ->whereDate('expense_date', '<=', $endDate->toDateString())
            ->orderBy('expense_date')
            ->get();

        return match ($data['format']) {
            'csv' => $this->downloadCsv($appointments, $expenses, $report, $currency, $periodLabel, $startDate, $endDate),
            'pdf' => $this->downloadPdf($appointments, $expenses, $report, $currency, $periodLabel, $startDate, $endDate),
        };
    }

    private function resolvePeriod(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        return match ($period) {
            'today' => [...AppointmentTimePolicy::todayRange(), 'Сегодня'],
            'week' => [...AppointmentTimePolicy::weekRange(), 'Текущая неделя'],
            'month' => [...AppointmentTimePolicy::monthRange(), 'Текущий месяц'],
            'custom' => (function () use ($dateFrom, $dateTo): array {
                [$startDate, $endDate] = AppointmentTimePolicy::customRange((string) $dateFrom, (string) $dateTo);

                return [
                    $startDate,
                    $endDate,
                    'Период: ' . $startDate->format('d.m.Y') . ' — ' . $endDate->format('d.m.Y'),
                ];
            })(),
        };
    }

    private function downloadCsv($appointments, $expenses, FinancialReportVO $report, string $currency, string $periodLabel, Carbon $startDate, Carbon $endDate)
    {
        $generatedAt = AppointmentTimePolicy::now();
        $fileName = 'financial-report-' . $generatedAt->format('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () use ($appointments, $expenses, $report, $currency, $periodLabel, $startDate, $endDate, $generatedAt) {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Отчёт о доходах'], ';', '"', '');
            fputcsv($handle, [$periodLabel], ';', '"', '');
            fputcsv($handle, ['Период', $startDate->format('d.m.Y') . ' — ' . $endDate->format('d.m.Y')], ';', '"', '');
            fputcsv($handle, ['Сформирован', $generatedAt->format('d.m.Y H:i')], ';', '"', '');
            fputcsv($handle, [], ';', '"', '');

            fputcsv($handle, ['Сводка'], ';', '"', '');
            fputcsv($handle, ['Доход', number_format($report->revenue, 2, '.', ''), \App\Support\CsvCell::text($currency)], ';', '"', '');
            fputcsv($handle, ['Расходы', number_format($report->expenses, 2, '.', ''), \App\Support\CsvCell::text($currency)], ';', '"', '');
            fputcsv($handle, ['Налог', number_format($report->tax, 2, '.', ''), \App\Support\CsvCell::text($currency)], ';', '"', '');
            fputcsv($handle, ['Чистая прибыль', number_format($report->netProfit, 2, '.', ''), \App\Support\CsvCell::text($currency)], ';', '"', '');
            fputcsv($handle, ['Чистая прибыль после налога', number_format($report->netAfterTax, 2, '.', ''), \App\Support\CsvCell::text($currency)], ';', '"', '');
            fputcsv($handle, ['Средний чек', number_format($report->averageCheck, 2, '.', ''), \App\Support\CsvCell::text($currency)], ';', '"', '');
            fputcsv($handle, ['Количество выполненных', $report->completedOrders], ';', '"', '');
            fputcsv($handle, [], ';', '"', '');

            fputcsv($handle, ['Доходы'], ';', '"', '');
            fputcsv($handle, ['Дата', 'Время', 'Клиент', 'Услуга', 'Сумма', 'Оплата'], ';', '"', '');

            foreach ($appointments as $appointment) {
                fputcsv($handle, [
                    $appointment->start_at->format('d.m.Y'),
                    $appointment->start_at->format('H:i'),
                    \App\Support\CsvCell::text($appointment->client?->name ?? '-'),
                    \App\Support\CsvCell::text($appointment->service?->name ?? '-'),
                    number_format((float) $appointment->price_at_moment, 2, '.', '') . ' ' . $currency,
                    $this->paymentTypeLabel((string) $appointment->payment_type),
                ], ';', '"', '');
            }

            fputcsv($handle, [], ';', '"', '');
            fputcsv($handle, ['Расходы'], ';', '"', '');
            fputcsv($handle, ['Дата', 'Тип', 'Наименование', 'Сумма'], ';', '"', '');

            foreach ($expenses as $expense) {
                fputcsv($handle, [
                    $expense->expense_date->format('d.m.Y'),
                    $this->expenseTypeLabel((string) $expense->type),
                    \App\Support\CsvCell::text((string) $expense->title),
                    number_format((float) $expense->amount, 2, '.', '') . ' ' . $currency,
                ], ';', '"', '');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function downloadPdf($appointments, $expenses, FinancialReportVO $report, string $currency, string $periodLabel, Carbon $startDate, Carbon $endDate)
    {
        $generatedAt = AppointmentTimePolicy::now();
        $fileName = 'financial-report-' . $generatedAt->format('Y-m-d_H-i') . '.pdf';

        $html = view('reports.financial-report-pdf', [
            'periodLabel' => $periodLabel,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'report' => $report,
            'appointments' => $appointments,
            'expenses' => $expenses,
            'currency' => $currency,
            'generatedAt' => $generatedAt,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function paymentTypeLabel(string $type): string
    {
        return match ($type) {
            'cash' => 'Наличные',
            'transfer' => 'Перевод',
            'card' => 'Карта',
            default => 'Не указан',
        };
    }

    private function expenseTypeLabel(string $type): string
    {
        return match ($type) {
            'rent' => 'Аренда',
            'material' => 'Материалы',
            'other' => 'Прочее',
            default => 'Прочее',
        };
    }
}

