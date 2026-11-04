<?php

namespace App\Http\Controllers;

use App\Models\FixedExpense;
use App\Models\PersonalExpense;
use App\Services\FixedExpenses;
use App\Services\Ledger;
use App\Services\MonthSummary;
use App\Services\PersonalExpenses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PersonalExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $current = Carbon::now()->format('Y-m');
        $requested = (string) $request->query('mes');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) ? $requested : $current;
        [$fixed, $fixedRateFailed] = FixedExpenses::withCurrentRate($user, $month);
        [$report, $rateFailed] = PersonalExpenses::withCurrentRate($user, $month);

        $date = Carbon::createFromFormat('!Y-m', $month);

        return view('personal.index', [
            ...$report, 'month' => $month, 'isCurrent' => $month === $current, 'rateFailed' => $rateFailed || $fixedRateFailed,
            'goal' => MonthSummary::goal(MonthSummary::business($user, $month, $fixed), $report),
            'editing' => $request->integer('editar') > 0 ? Ledger::personalExpenses($user)->firstWhere('id', $request->integer('editar')) : null,
            'kind' => $request->query('tipo') === 'recurrente' ? 'monthly' : 'once',
            'defaultDate' => $month === $current ? Carbon::now()->toDateString() : $date->toDateString(),
            'monthLabel' => $date->locale('es')->translatedFormat('F Y'),
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month < $current ? $date->copy()->addMonth()->format('Y-m') : null,
        ]);
    }

    // Un mismo formulario carga un gasto de una sola vez (con fecha) o uno que se repite todos los meses (con mes de inicio y moneda).
    public function store(Request $request): RedirectResponse
    {
        $monthly = $request->input('kind') === 'monthly';
        $data = $request->validate([
            'kind' => ['required', 'in:once,monthly'],
            ...$this->rules($monthly ? 160 : 180),
            'date' => ['exclude_unless:kind,once', 'required', 'date_format:Y-m-d'],
            'effective_from' => ['exclude_unless:kind,monthly', 'required', 'date_format:Y-m'],
            'currency' => ['exclude_unless:kind,monthly', 'required', Rule::in(array_keys(FixedExpense::CURRENCIES))],
        ], ['category.required' => 'Elegí una categoría.', 'kind.required' => 'Elegí si el gasto es de una sola vez o de todos los meses.']);

        if (! $monthly) {
            $expense = $request->user()->personalExpenses()->create(Arr::only($data, ['description', 'category', 'amount', 'date']));

            return redirect()->route('personal.index', ['mes' => $expense->date->format('Y-m')])->with('success', 'Gasto guardado.');
        }

        DB::transaction(fn () => $request->user()->personalRecurringExpenses()
            ->create(['name' => $data['description'], 'category' => $data['category'], 'currency' => $data['currency']])
            ->amounts()->create(Arr::only($data, ['amount', 'effective_from'])));
        $from = Carbon::createFromFormat('!Y-m', $data['effective_from'])->locale('es')->translatedFormat('F Y');

        // Un gasto que empieza en un mes futuro se muestra desde el mes en curso.
        return redirect()->route('personal.index', ['mes' => min($data['effective_from'], Carbon::now()->format('Y-m'))])
            ->with('success', 'Gasto recurrente agregado: se suma todos los meses desde '.$from.'.');
    }

    public function update(Request $request, PersonalExpense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 404);
        $expense->update($this->validated($request));

        return redirect()->route('personal.index', ['mes' => $expense->date->format('Y-m')])->with('success', 'Gasto actualizado.');
    }

    public function destroy(Request $request, PersonalExpense $expense): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 404);
        $expense->delete();

        return redirect()->route('personal.index', ['mes' => $expense->date->format('Y-m')])->with('success', 'Gasto eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([...$this->rules(180), 'date' => ['required', 'date_format:Y-m-d']], ['category.required' => 'Elegí una categoría.']);
    }

    // El nombre de un recurrente admite menos caracteres que la descripción de un gasto.
    private function rules(int $maxDescription): array
    {
        return [
            'description' => ['required', 'string', 'max:'.$maxDescription],
            'category' => ['required', Rule::in(array_keys(PersonalExpense::CATEGORIES))],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999'],
        ];
    }
}
