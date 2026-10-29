<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->transactions()->with('client')->latest('date')->latest('id');
        if (in_array($request->input('area'), ['business', 'personal'], true)) $query->where('area', $request->input('area'));
        return view('movements.index', ['transactions' => $query->limit(150)->get(), 'filter' => $request->input('area', 'all')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'type' => ['required', 'in:income,expense'], 'area' => ['required', 'in:business,personal'],
            'description' => ['required', 'string', 'max:180'], 'category' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'], 'date' => ['required', 'date'],
        ]);
        $request->user()->transactions()->create($input);
        return back()->with('success', 'Movimiento guardado.');
    }

    public function export(Request $request): Response
    {
        $transactions = $request->user()->transactions()->with('client')->orderBy('date')->orderBy('id')->get();
        $content = "\xEF\xBB\xBFFecha;Tipo;Área;Descripción;Categoría;Cliente;Mes de abono;Importe\r\n";
        foreach ($transactions as $transaction) {
            $row = [
                $transaction->date->format('Y-m-d'), $transaction->type === 'income' ? 'Ingreso' : 'Gasto',
                $transaction->area === 'personal' ? 'Personal' : 'Negocio', $transaction->description,
                $transaction->category, $transaction->client?->name, $transaction->billing_month,
                number_format((float) $transaction->amount, 2, ',', '.'),
            ];
            $content .= implode(';', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row))."\r\n";
        }

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mis-cuentas-movimientos-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);
        $transaction->delete();
        return back()->with('success', 'Movimiento eliminado.');
    }

    public function personal(Request $request): View
    {
        $now = Carbon::now();
        $transactions = $request->user()->transactions()->where('area', 'personal')->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->latest('date')->latest('id')->get();
        return view('personal.index', [
            'transactions' => $transactions, 'income' => (float) $transactions->where('type', 'income')->sum('amount'),
            'expenses' => (float) $transactions->where('type', 'expense')->sum('amount'),
            'monthLabel' => $now->locale('es')->translatedFormat('F Y'),
        ]);
    }
}
