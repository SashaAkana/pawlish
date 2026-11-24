<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Support\AppointmentTimePolicy;
use Illuminate\Console\Command;

class CreateRecurringExpenses extends Command
{
    protected $signature = 'expenses:create-recurring';
    protected $description = 'Создать повторяющиеся расходы за текущий месяц';

    public function handle(): int
    {
        $now = AppointmentTimePolicy::now();
        $month = $now->copy()->startOfMonth()->toDateString();
        $templates = Expense::query()->where('is_recurring', true)
            ->where('recurring_period', 'monthly')->whereDate('expense_date', '<', $month)->get();
        $count = 0;
        foreach ($templates as $template) {
            $identity = ['recurring_source_id' => $template->id, 'recurring_month' => $month];
            if (Expense::query()->where($identity)->exists()) {
                continue;
            }
            $title = sprintf('%s (%s)', $template->title, $now->format('m.Y'));
            // Recognize old-version rows without assuming that matching names imply identity.
            $legacy = Expense::query()->whereNull('recurring_source_id')->where('is_recurring', false)
                ->where('title', $title)->where('type', $template->type)->whereDate('expense_date', $month);
            if ($legacy->exists()) {
                $matching = $templates->filter(fn ($other) => $other->title === $template->title && $other->type === $template->type);
                if ($matching->count() !== 1 || $legacy->count() !== 1) {
                    $this->warn('Неоднозначный старый расход: проверьте строки вручную (шаблон #' . $template->id . ').');
                    continue;
                }
                $legacy->update($identity);
                continue;
            }
            // The unique index also protects concurrent scheduler/CLI runs.
            $count += Expense::query()->insertOrIgnore($identity + [
                'type' => $template->type, 'title' => $title, 'amount' => $template->amount,
                'expense_date' => $month, 'is_recurring' => false, 'recurring_period' => 'none',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info("Создано повторяющихся расходов: {$count}");
        return self::SUCCESS;
    }
}
