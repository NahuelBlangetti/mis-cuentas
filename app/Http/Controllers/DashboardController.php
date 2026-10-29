<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $now = Carbon::now();
        $month = $now->format('Y-m');
        $clients = $user->clients()->where('active', true)->orderBy('name')->get();
        $monthTransactions = $user->transactions()->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->get();
        $payments = $user->transactions()->whereNotNull('client_id')->where('billing_month', $month)->get()->groupBy('client_id');
        $expected = (float) $clients->sum('monthly_fee');
        $collected = (float) $payments->flatten()->sum('amount');

        return view('dashboard', [
            'clients' => $clients, 'payments' => $payments, 'expected' => $expected,
            'collected' => $collected, 'pending' => max(0, $expected - $collected),
            'businessExpenses' => (float) $monthTransactions->where('area', 'business')->where('type', 'expense')->sum('amount'),
            'recent' => $user->transactions()->with('client')->latest('date')->latest('id')->limit(7)->get(),
            'monthLabel' => $now->locale('es')->translatedFormat('F Y'),
        ]);
    }
}
