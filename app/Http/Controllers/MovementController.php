<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->transactions()->with('client')->latest('date')->latest('id');
        if (in_array($request->input('area'), ['business', 'personal'], true)) $query->where('area', $request->input('area'));
        return view('movements.index', [
            'transactions' => $query->paginate(50)->withQueryString(), 'filter' => $request->input('area', 'all'),
            'clients' => $request->user()->clients()->orderByDesc('active')->orderBy('name')->get(['id', 'name', 'alias', 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->transactions()->create($this->validated($request));
        return back()->with('success', 'Movimiento guardado.');
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);
        // Un cobro de abono sigue siendo un ingreso del negocio del mismo cliente, imputado al mismo mes: solo cambian sus datos.
        $transaction->update($this->validated($request, ! $transaction->isSubscriptionPayment()));
        return back()->with('success', 'Movimiento actualizado.');
    }

    public function export(Request $request): Response
    {
        $transactions = $request->user()->transactions()->with('client')->orderBy('date')->orderBy('id')->get();
        $content = "\xEF\xBB\xBFFecha;Tipo;Área;Descripción;Categoría;Cliente;Mes de abono;Importe\r\n";
        foreach ($transactions as $transaction) {
            $row = [
                $transaction->date->format('Y-m-d'), $transaction->type === 'income' ? 'Ingreso' : 'Gasto',
                $transaction->area === 'personal' ? 'Personal' : 'Negocio', $transaction->description,
                $transaction->category, $transaction->clientLabel(), $transaction->billing_month,
                number_format((float) $transaction->amount, 2, ',', '.'),
            ];
            $content .= implode(';', array_map(fn ($value) => $this->csvCell($value), $row))."\r\n";
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
        $current = Carbon::now()->startOfMonth();
        $requested = (string) $request->query('mes');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) ? Carbon::createFromFormat('!Y-m', $requested) : $current->copy();
        $transactions = $request->user()->transactions()->where('area', 'personal')->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->latest('date')->latest('id')->get();
        return view('personal.index', [
            'transactions' => $transactions, 'income' => (float) $transactions->where('type', 'income')->sum('amount'),
            'expenses' => (float) $transactions->where('type', 'expense')->sum('amount'),
            'monthLabel' => $month->locale('es')->translatedFormat('F Y'),
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->lt($current) ? $month->copy()->addMonth()->format('Y-m') : null,
        ]);
    }

    // El cliente es opcional y solo aplica al negocio: uno registrado (sin imputarse a ningún abono) o uno ocasional, con su nombre.
    private function validated(Request $request, bool $withKind = true): array
    {
        $clientIds = $request->user()->clients()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $data = $request->validate([
            ...($withKind ? [
                'type' => ['required', 'in:income,expense'], 'area' => ['required', 'in:business,personal'],
                'client' => ['nullable', Rule::in(['occasional', ...$clientIds])],
                'client_name' => ['nullable', 'required_if:client,occasional', 'string', 'max:160'],
            ] : []),
            'description' => ['required', 'string', 'max:180'], 'category' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'], 'date' => ['required', 'date'],
        ], ['client.in' => 'Elegí uno de tus clientes o un cliente ocasional.', 'client_name.required_if' => 'Escribí el nombre del cliente ocasional.']);
        if (! $withKind) return $data;

        $client = $data['area'] === 'business' ? ($data['client'] ?? null) : null;
        unset($data['client']);
        return [...$data, 'client_id' => is_numeric($client) ? (int) $client : null, 'client_name' => $client === 'occasional' ? $data['client_name'] : null];
    }

    // Una celda que empieza con =, +, -, @ (aunque haya espacios antes) o con un carácter de control se ejecuta
    // como fórmula en la planilla; el apóstrofo la deja como texto.
    private function csvCell(mixed $value): string
    {
        $value = (string) $value;
        $first = ltrim($value, " \t\r\n")[0] ?? '';
        if (in_array($first, ['=', '+', '-', '@'], true) || in_array($value[0] ?? '', ["\t", "\r", "\n"], true)) $value = "'".$value;
        return '"'.str_replace('"', '""', $value).'"';
    }
}
