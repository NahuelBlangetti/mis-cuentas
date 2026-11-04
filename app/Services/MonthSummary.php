<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class MonthSummary
{
    // Resultado del negocio por fecha de cada movimiento: un cobro cuenta en el mes en que entró, aunque pague otro mes.
    // Los gastos fijos suman tu parte en pesos; los pendientes (sin participación o sin cotización) no suman.
    // $fixed es el reporte de FixedExpenses del mismo mes.
    public static function business(User $user, string $month, array $fixed): array
    {
        $date = Carbon::createFromFormat('!Y-m', $month);
        $business = $user->transactions()->where('area', 'business')
            ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])->get();
        $income = $business->where('type', 'income');

        $summary = [
            'subscriptionIncome' => round((float) $income->whereNotNull('billing_month')->sum('amount'), 2),
            'otherIncome' => round((float) $income->whereNull('billing_month')->sum('amount'), 2),
            'recordedExpenses' => round((float) $business->where('type', 'expense')->sum('amount'), 2),
            'fixedExpenses' => $fixed['totalArs'],
            'fixedPending' => $fixed['pendingShare'] + $fixed['pendingRate'],
        ];
        $summary['income'] = round($summary['subscriptionIncome'] + $summary['otherIncome'], 2);
        $summary['expenses'] = round($summary['recordedExpenses'] + $summary['fixedExpenses'], 2);
        $summary['result'] = round($summary['income'] - $summary['expenses'], 2);

        return $summary;
    }

    // Objetivo personal: que lo que cobrás a tus clientes en el mes (abonos y pagos extra) pague tus gastos
    // personales y los del negocio. Lo pendiente de cotización o de participación no suma a los gastos.
    // $personal es el reporte de PersonalExpenses del mismo mes.
    public static function goal(array $business, array $personal): array
    {
        $expenses = round($business['expenses'] + $personal['total'], 2);

        return [
            'subscriptionIncome' => $business['subscriptionIncome'], 'otherIncome' => $business['otherIncome'], 'income' => $business['income'],
            'personalExpenses' => $personal['total'], 'businessExpenses' => $business['expenses'], 'expenses' => $expenses,
            'balance' => round($business['income'] - $expenses, 2),
            // Se redondea hacia abajo: un 99,6 % todavía no cubre los gastos.
            'coverage' => $expenses > 0 ? (int) floor($business['income'] / $expenses * 100) : null,
            'pending' => $business['fixedPending'] + $personal['pendingRate'],
        ];
    }
}
