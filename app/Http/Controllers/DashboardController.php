<?php

namespace App\Http\Controllers;

use App\Services\Billing;
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
        $monthTransactions = $user->transactions()->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->get();

        return view('dashboard', [
            'clients' => $clients, 'balances' => $balances,
            'expected' => round($balances->sum('charged'), 2), 'collected' => round($balances->sum('paid'), 2),
            'pending' => round($balances->sum('total'), 2), 'arrears' => round($balances->sum('arrears'), 2),
            'businessExpenses' => (float) $monthTransactions->where('area', 'business')->where('type', 'expense')->sum('amount'),
            'recent' => $user->transactions()->with('client')->latest('date')->latest('id')->limit(7)->get(),
            'monthLabel' => $now->locale('es')->translatedFormat('F Y'),
        ]);
    }
}
