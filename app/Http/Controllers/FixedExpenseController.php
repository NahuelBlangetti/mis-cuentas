<?php

namespace App\Http\Controllers;

use App\Models\FixedExpense;
use App\Models\FixedExpenseAmount;
use App\Services\FixedExpenses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FixedExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $current = Carbon::now()->startOfMonth();
        $requested = (string) $request->query('mes');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) && $requested <= $current->format('Y-m') ? $requested : $current->format('Y-m');
        [$report, $rateFailed] = FixedExpenses::withCurrentRate($user, $month);

        $date = Carbon::createFromFormat('!Y-m', $month);
        return view('fixed-expenses.index', [
            ...$report, 'month' => $month, 'isCurrent' => $month === $current->format('Y-m'), 'rateFailed' => $rateFailed,
            'monthLabel' => $date->locale('es')->translatedFormat('F Y'),
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->lt($current) ? $date->copy()->addMonth()->format('Y-m') : null,
            'resources' => $user->infrastructureResources()->orderByDesc('active')->orderBy('name')->get(['id', 'name', 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null, [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999'],
            'effective_from' => ['required', 'date_format:Y-m'],
        ]);
        $amount = Arr::only($data, ['amount', 'effective_from']);
        DB::transaction(fn () => $request->user()->fixedExpenses()->create(Arr::except($data, ['amount', 'effective_from']))->amounts()->create($amount));

        return redirect()->route('fixed-expenses.index', ['mes' => $this->visibleMonth($amount['effective_from'])])->with('success', 'Gasto fijo agregado.');
    }

    // La moneda no se cambia: reinterpretaría todos los importes ya cargados.
    public function update(Request $request, FixedExpense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 404);
        $expense->update($this->validated($request, $expense));

        return back()->with('success', 'Gasto fijo actualizado.');
    }

    public function destroy(Request $request, FixedExpense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 404);
        $expense->delete();

        return back()->with('success', 'Gasto fijo eliminado.');
    }

    // Un cambio de plan es un importe nuevo con su mes de vigencia; 0 indica que desde ese mes se dejó de pagar.
    public function storeAmount(Request $request, FixedExpense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m', Rule::unique('fixed_expense_amounts')->where('fixed_expense_id', $expense->id)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ], ['effective_from.unique' => 'Ya hay un importe desde ese mes. Eliminalo antes de cargar otro.']);
        $expense->amounts()->create($data);

        return back()->with('success', (float) $data['amount'] === 0.0 ? 'Gasto dado de baja desde ese mes.' : 'Importe registrado.');
    }

    public function destroyAmount(Request $request, FixedExpense $expense, FixedExpenseAmount $amount): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id && $amount->fixed_expense_id === $expense->id, 404);
        $amount->delete();

        return back()->with('success', 'Importe eliminado.');
    }

    // Solo el mes en curso toma el precio de Arq: aplicar el precio de hoy a un mes cerrado lo recalcularía.
    public function refreshRate(Request $request): RedirectResponse
    {
        $quote = FixedExpenses::fetchRate();
        if (! $quote) return back()->withErrors(['rate' => 'No se pudo obtener la cotización de Arq. Probá más tarde o cargala a mano.']);
        FixedExpenses::storeRate($request->user(), Carbon::now()->format('Y-m'), $quote['rate'], 'arq', $quote['quoted_at']);

        return back()->with('success', 'Cotización actualizada: '.money($quote['rate']).' por dólar.');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.Carbon::now()->format('Y-m')],
            'rate' => ['required', 'numeric', 'decimal:0,4', 'min:1', 'max:9999999999'],
        ], ['month.before_or_equal' => 'No se puede cargar la cotización de un mes que todavía no empezó.']);
        FixedExpenses::storeRate($request->user(), $data['month'], (float) $data['rate'], 'manual');

        return back()->with('success', 'Cotización guardada para '.Carbon::createFromFormat('!Y-m', $data['month'])->locale('es')->translatedFormat('F Y').'.');
    }

    private function validated(Request $request, ?FixedExpense $expense, array $extra = []): array
    {
        return $request->validate([
            ...$extra,
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(array_keys(FixedExpense::CATEGORIES))],
            ...($expense ? [] : ['currency' => ['required', Rule::in(array_keys(FixedExpense::CURRENCIES))]]),
            'infrastructure_resource_id' => ['nullable', 'integer', Rule::exists('infrastructure_resources', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['infrastructure_resource_id.exists' => 'Elegí uno de tus recursos de infraestructura.']);
    }

    // Un gasto que empieza en un mes futuro se muestra desde el mes en curso.
    private function visibleMonth(string $month): string
    {
        return min($month, Carbon::now()->format('Y-m'));
    }
}
