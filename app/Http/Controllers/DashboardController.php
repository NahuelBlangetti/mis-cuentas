<?php

namespace App\Http\Controllers;

use App\Services\Billing;
use App\Services\FixedExpenses;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $now = Carbon::now();
        $month = $now->format('Y-m');
        Billing::sync($user);
        $balances = Billing::balances($user, $month);
        $clients = $user->clients()->orderBy('name')->get()->filter(fn ($client) => $client->active || $balances[$client->id]['total'] > 0)->values();
        $business = $user->transactions()->where('area', 'business')->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->get();
        $income = $business->where('type', 'income');
        [$fixed, $rateFailed] = FixedExpenses::withCurrentRate($user, $month);

        // Resultado del negocio por fecha de cada movimiento: un cobro cuenta en el mes en que entró, aunque pague otro mes.
        // Los gastos fijos suman tu parte en pesos; los pendientes (sin participación o sin cotización) no suman.
        $summary = [
            'subscriptionIncome' => round((float) $income->whereNotNull('billing_month')->sum('amount'), 2),
            'otherIncome' => round((float) $income->whereNull('billing_month')->sum('amount'), 2),
            'recordedExpenses' => round((float) $business->where('type', 'expense')->sum('amount'), 2),
            'fixedExpenses' => $fixed['totalArs'],
            'fixedPending' => $fixed['pendingShare'] + $fixed['pendingRate'],
            'rateFailed' => $rateFailed,
        ];
        $summary['income'] = round($summary['subscriptionIncome'] + $summary['otherIncome'], 2);
        $summary['expenses'] = round($summary['recordedExpenses'] + $summary['fixedExpenses'], 2);
        $summary['result'] = round($summary['income'] - $summary['expenses'], 2);

        return view('dashboard', [
            'clients' => $clients, 'balances' => $balances, 'summary' => $summary,
            'expected' => round($balances->sum('charged'), 2), 'collected' => round($balances->sum('paid'), 2),
            'pending' => round($balances->sum('total'), 2), 'arrears' => round($balances->sum('arrears'), 2),
            'recent' => $user->transactions()->with('client')->latest('date')->latest('id')->limit(7)->get(),
            'monthLabel' => $now->locale('es')->translatedFormat('F Y'),
        ]);
    }
}
