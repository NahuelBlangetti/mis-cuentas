<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\Billing;
use App\Services\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $month = Carbon::now()->format('Y-m');
        Billing::sync($user);
        $clients = $user->clients()->withCount(['transactions' => fn ($q) => $q->where('billing_month', $month)])->orderByDesc('active')->orderBy('name')->get();
        $active = $clients->where('active', true);
        return view('clients.index', [
            'clients' => $clients, 'balances' => Billing::balances($user, $month), 'month' => $month,
            'activeCount' => $active->count(), 'systemsCount' => $active->sum('systems'), 'paymentsCount' => $clients->sum('transactions_count'),
            'monthlyTotal' => (float) $active->sum('monthly_fee'), 'monthLabel' => Carbon::now()->locale('es')->translatedFormat('F Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $request->user()->clients()->create($this->validated($request));
        return redirect()->route('clients.installations.index', $client)->with('success', 'Cliente agregado. Cargá sus instalaciones y bases de producción.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $client->update($this->validated($request));
        // El nuevo abono rige desde el mes actual; los meses anteriores conservan el monto que tenían.
        if ($client->wasChanged('monthly_fee')) $client->charges()->where('billing_month', Carbon::now()->format('Y-m'))->update(['amount' => $client->monthly_fee]);
        return back()->with('success', 'Cliente actualizado.');
    }

    public function toggle(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $client = DB::transaction(function () use ($request, $client) {
            // Pausar y cobrar bloquean al cliente: un cobro simultáneo no puede quedar sin el abono que paga.
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            Billing::sync($request->user());
            $client->update(['active' => ! $client->active]);
            $client->active ? Billing::resume($client) : Billing::pause($client);
            return $client;
        });
        return back()->with('success', $client->active ? 'Cliente reactivado.' : 'Cliente pausado.');
    }

    public function payment(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $input = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'date' => ['required', 'date'], 'billing_month' => ['required', 'date_format:Y-m'],
        ]);
        DB::transaction(function () use ($request, $client, $input) {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            $month = Carbon::now()->format('Y-m');
            Billing::sync($request->user());
            // Solo se aceptan los meses que ofrece el formulario, calculados con el cliente ya bloqueado.
            $allowed = Billing::paymentMonths(Billing::balances($request->user(), $month)[$client->id], $month, $client->active);
            if (! array_key_exists($input['billing_month'], $allowed)) {
                throw ValidationException::withMessages(['billing_month' => $client->active ? 'Elegí un mes adeudado, el actual o el siguiente.' : 'Un cliente pausado solo puede pagar meses adeudados.']);
            }
            $request->user()->transactions()->create([
                'client_id' => $client->id, 'type' => 'income', 'area' => 'business',
                'description' => 'Abono · '.$client->name, 'category' => 'Abono de cliente',
                'amount' => $input['amount'], 'date' => $input['date'], 'billing_month' => $input['billing_month'],
            ]);
        });
        return back()->with('success', 'Cobro registrado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'systems' => ['required', 'integer', 'min:1', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (WhatsApp::normalize($value) === null) $fail('Ingresá el celular con código de área, sin 0 ni 15. Ej. 351 1234567.');
            }],
            'monthly_fee' => ['required', 'numeric', 'min:0', 'max:9999999999'], 'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        return [...$data, 'phone' => WhatsApp::normalize($data['phone'] ?? null)];
    }
}
