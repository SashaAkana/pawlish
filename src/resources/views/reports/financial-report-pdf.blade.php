<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Финансовый отчёт</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 20px 0 8px; }
        .muted { color: #6b7280; font-size: 11px; }
        .summary { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .summary td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        .summary td:first-child { font-weight: bold; background: #f9fafb; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.grid th, table.grid td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        table.grid th { background: #f3f4f6; font-weight: bold; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Отчёт о доходах</h1>
    <div class="muted">{{ $periodLabel }}</div>
    <div class="muted">Период: {{ $startDate->format('d.m.Y') }} — {{ $endDate->format('d.m.Y') }}</div>
    <div class="muted">Сформирован: {{ $generatedAt->format('d.m.Y H:i') }}</div>

    <h2>Сводка</h2>
    <table class="summary">
        <tr><td>Доход</td><td>{{ number_format($report->revenue, 2, '.', ' ') }} {{ $currency }}</td></tr>
        <tr><td>Расходы</td><td>{{ number_format($report->expenses, 2, '.', ' ') }} {{ $currency }}</td></tr>
        <tr><td>Налог</td><td>{{ number_format($report->tax, 2, '.', ' ') }} {{ $currency }}</td></tr>
        <tr><td>Чистая прибыль</td><td>{{ number_format($report->netProfit, 2, '.', ' ') }} {{ $currency }}</td></tr>
        <tr><td>Чистая прибыль после налога</td><td>{{ number_format($report->netAfterTax, 2, '.', ' ') }} {{ $currency }}</td></tr>
        <tr><td>Средний чек</td><td>{{ number_format($report->averageCheck, 2, '.', ' ') }} {{ $currency }}</td></tr>
        <tr><td>Количество выполненных</td><td>{{ $report->completedOrders }}</td></tr>
    </table>

    <h2>Доходы</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Время</th>
                <th>Клиент</th>
                <th>Услуга</th>
                <th class="right">Сумма</th>
                <th>Оплата</th>
            </tr>
        </thead>
        <tbody>
            @forelse($appointments as $appointment)
                <tr>
                    <td>{{ $appointment->start_at->format('d.m.Y') }}</td>
                    <td>{{ $appointment->start_at->format('H:i') }}</td>
                    <td>{{ $appointment->client?->name ?? '-' }}</td>
                    <td>{{ $appointment->service?->name ?? '-' }}</td>
                    <td class="right">{{ number_format((float) $appointment->price_at_moment, 2, '.', ' ') }} {{ $currency }}</td>
                    <td>{{ match((string) $appointment->payment_type) {
                        'cash' => 'Наличные',
                        'transfer' => 'Перевод',
                        'card' => 'Карта',
                        default => 'Не указан',
                    } }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Нет данных за выбранный период.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Расходы</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Тип</th>
                <th>Наименование</th>
                <th class="right">Сумма</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $expense)
                <tr>
                    <td>{{ $expense->expense_date->format('d.m.Y') }}</td>
                    <td>{{ match((string) $expense->type) {
                        'rent' => 'Аренда',
                        'material' => 'Материалы',
                        'other' => 'Прочее',
                        default => 'Прочее',
                    } }}</td>
                    <td>{{ $expense->title }}</td>
                    <td class="right">{{ number_format((float) $expense->amount, 2, '.', ' ') }} {{ $currency }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Нет данных за выбранный период.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

