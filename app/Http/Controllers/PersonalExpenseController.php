<?php

namespace App\Http\Controllers;

use App\Models\PersonalExpense;
use App\Services\FixedExpenses;
use App\Services\MonthSummary;
use App\Services\PersonalExpenses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'editing' => $request->integer('editar') > 0 ? $user->personalExpenses()->find($request->integer('editar')) : null,
            'defaultDate' => $month === $current ? Carbon::now()->toDateString() : $date->toDateString(),
            'monthLabel' => $date->locale('es')->translatedFormat('F Y'),
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month < $current ? $date->copy()->addMonth()->format('Y-m') : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $expense = $request->user()->personalExpenses()->create($this->validated($request));

        return redirect()->route('personal.index', ['mes' => $expense->date->format('Y-m')])->with('success', 'Gasto guardado.');
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
        return $request->validate([
            'description' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(array_keys(PersonalExpense::CATEGORIES))],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999'],
            'date' => ['required', 'date_format:Y-m-d'],
        ], ['category.required' => 'Elegí una categoría.']);
    }
}
