<?php

namespace App\Http\Controllers;

use App\Services\Billing;
use App\Services\FixedExpenses;
use App\Services\MonthSummary;
use App\Services\PersonalExpenses;
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
        [$fixed, $rateFailed] = FixedExpenses::withCurrentRate($user, $month);
        [$personal, $personalRateFailed] = PersonalExpenses::withCurrentRate($user, $month);
        $summary = [...MonthSummary::business($user, $month, $fixed), 'rateFailed' => $rateFailed || $personalRateFailed];

        return view('dashboard', [
            'clients' => $clients, 'balances' => $balances, 'summary' => $summary, 'goal' => MonthSummary::goal($summary, $personal),
            'expected' => round($balances->sum('charged'), 2), 'collected' => round($balances->sum('paid'), 2),
            'pending' => round($balances->sum('total'), 2), 'arrears' => round($balances->sum('arrears'), 2),
            'recent' => $user->transactions()->with('client')->latest('date')->latest('id')->limit(7)->get(),
            'monthLabel' => $now->locale('es')->translatedFormat('F Y'),
        ]);
    }
}
