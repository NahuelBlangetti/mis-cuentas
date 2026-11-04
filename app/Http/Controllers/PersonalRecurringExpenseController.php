<?php

namespace App\Http\Controllers;

use App\Models\PersonalExpense;
use App\Models\PersonalRecurringAmount;
use App\Models\PersonalRecurringExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// El alta está en PersonalExpenseController::store, en el mismo formulario que los gastos de una sola vez.
class PersonalRecurringExpenseController extends Controller
{
    // La moneda no se cambia: reinterpretaría todos los importes ya cargados.
    public function update(Request $request, PersonalRecurringExpense $recurring): RedirectResponse
    {
        abort_unless($recurring->user_id === $request->user()->id, 404);
        $recurring->update($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(array_keys(PersonalExpense::CATEGORIES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['category.required' => 'Elegí una categoría.']));

        return back()->with('success', 'Gasto recurrente actualizado.');
    }

    public function destroy(Request $request, PersonalRecurringExpense $recurring): RedirectResponse
    {
        abort_unless($recurring->user_id === $request->user()->id, 404);
        $recurring->delete();

        return back()->with('success', 'Gasto recurrente eliminado.');
    }

    // Un cambio de precio es un importe nuevo con su mes de vigencia; 0 indica que desde ese mes se dejó de pagar.
    public function storeAmount(Request $request, PersonalRecurringExpense $recurring): RedirectResponse
    {
        abort_unless($recurring->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m', Rule::unique('personal_recurring_amounts')->where('recurring_expense_id', $recurring->id)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ], ['effective_from.unique' => 'Ya hay un importe desde ese mes. Eliminalo antes de cargar otro.']);
        $recurring->amounts()->create($data);

        return back()->with('success', (float) $data['amount'] === 0.0 ? 'Gasto dado de baja desde ese mes.' : 'Importe registrado.');
    }

    public function destroyAmount(Request $request, PersonalRecurringExpense $recurring, PersonalRecurringAmount $amount): RedirectResponse
    {
        abort_unless($recurring->user_id === $request->user()->id && $amount->recurring_expense_id === $recurring->id, 404);
        $amount->delete();

        return back()->with('success', 'Importe eliminado.');
    }
}
