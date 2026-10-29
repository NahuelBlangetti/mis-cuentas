<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $month = Carbon::now()->format('Y-m');
        $clients = $user->clients()->with(['transactions' => fn ($q) => $q->where('billing_month', $month)])->orderByDesc('active')->orderBy('name')->get();
        $active = $clients->where('active', true);
        return view('clients.index', [
            'clients' => $clients, 'activeCount' => $active->count(), 'systemsCount' => $active->sum('systems'),
            'monthlyTotal' => (float) $active->sum('monthly_fee'), 'monthLabel' => Carbon::now()->locale('es')->translatedFormat('F Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->clients()->create($request->validate($this->rules()));
        return back()->with('success', 'Cliente agregado.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $client->update($request->validate($this->rules()));
        return back()->with('success', 'Cliente actualizado.');
    }

    public function toggle(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $client->update(['active' => ! $client->active]);
        return back()->with('success', $client->active ? 'Cliente reactivado.' : 'Cliente pausado.');
    }

    public function payment(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $input = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'date' => ['required', 'date'], 'billing_month' => ['required', 'date_format:Y-m'],
        ]);
        $request->user()->transactions()->create([
            'client_id' => $client->id, 'type' => 'income', 'area' => 'business',
            'description' => 'Abono · '.$client->name, 'category' => 'Abono de cliente',
            'amount' => $input['amount'], 'date' => $input['date'], 'billing_month' => $input['billing_month'],
        ]);
        return back()->with('success', 'Cobro registrado.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'], 'systems' => ['required', 'integer', 'min:1', 'max:1000'],
            'monthly_fee' => ['required', 'numeric', 'min:0', 'max:9999999999'], 'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
