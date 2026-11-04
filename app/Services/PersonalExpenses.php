<?php

namespace App\Services;

use App\Models\PersonalExpense;
use App\Models\PersonalRecurringExpense;
use App\Models\User;
use Illuminate\Support\Carbon;

class PersonalExpenses
{
    private const ORDER = ['ok' => 0, 'pending_rate' => 0, 'not_started' => 1, 'ended' => 2];

    // Como en los gastos fijos, el mes en curso toma la cotización de Arq la primera vez que hace falta.
    // Devuelve [reporte, si falló la consulta].
    public static function withCurrentRate(User $user, string $month): array
    {
        $report = self::report($user, $month);
        if (! $report['needsRate'] || $report['rate']) {
            return [$report, false];
        }
        $stored = FixedExpenses::attemptCurrentRate($user, $month);

        return $stored ? [self::report($user, $month), false] : [$report, $stored === false];
    }

    // Gastos personales de un mes, en pesos: los recurrentes vigentes más los cargados con fecha en el mes.
    // Un recurrente en dólares sin cotización queda pendiente y no suma: nunca se asume una cotización.
    public static function report(User $user, string $month): array
    {
        $date = Carbon::createFromFormat('!Y-m', $month);
        $rate = Ledger::exchangeRate($user, $month);
        $recurring = Ledger::recurringExpenses($user)
            ->map(function (PersonalRecurringExpense $expense) use ($month, $rate) {
                $amount = $expense->amountFor($month);
                $ars = match (true) {
                    $amount === null => null,
                    $expense->currency === 'USD' => $rate ? (float) $amount->amount * (float) $rate->rate : null,
                    default => (float) $amount->amount,
                };
                $status = match (true) {
                    $amount === null => 'not_started',
                    (float) $amount->amount === 0.0 => 'ended',
                    $ars === null => 'pending_rate',
                    default => 'ok',
                };

                return ['expense' => $expense, 'amount' => $amount, 'status' => $status, 'ars' => $ars === null ? null : round($ars, 2)];
            })
            ->sortBy(fn ($row) => self::ORDER[$row['status']].'|'.$row['expense']->category.'|'.mb_strtolower($row['expense']->name))->values();
        $start = $date->copy()->startOfMonth()->toDateString();
        $end = $date->copy()->endOfMonth()->toDateString();
        $expenses = Ledger::personalExpenses($user)->filter(function (PersonalExpense $expense) use ($start, $end) {
            $day = $expense->date->toDateString();

            return $day >= $start && $day <= $end;
        })->sortByDesc(fn (PersonalExpense $expense) => $expense->date->format('Y-m-d').sprintf('%010d', $expense->id))->values();

        $counted = $recurring->where('status', 'ok');
        $recurringTotal = round($counted->sum('ars'), 2);
        $monthTotal = round((float) $expenses->sum('amount'), 2);
        $total = round($recurringTotal + $monthTotal, 2);
        $categories = collect(PersonalExpense::CATEGORIES)
            ->map(function ($label, $key) use ($counted, $expenses, $total) {
                $amount = round($counted->filter(fn ($row) => $row['expense']->category === $key)->sum('ars') + (float) $expenses->where('category', $key)->sum('amount'), 2);

                return ['label' => $label, 'amount' => $amount, 'percent' => $total > 0 ? (int) round($amount / $total * 100) : 0];
            })
            ->filter(fn ($category) => $category['amount'] > 0)->sortByDesc('amount');
        $current = $recurring->whereIn('status', ['ok', 'pending_rate']);

        return [
            'recurring' => $recurring, 'expenses' => $expenses, 'rate' => $rate, 'categories' => $categories,
            'recurringTotal' => $recurringTotal, 'monthTotal' => $monthTotal, 'total' => $total,
            'activeRecurring' => $current->count(),
            'pendingRate' => $current->where('status', 'pending_rate')->count(),
            'needsRate' => $current->contains(fn ($row) => $row['expense']->currency === 'USD'),
        ];
    }
}
