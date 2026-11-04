<?php

namespace App\Http\Controllers;

use App\Models\InfrastructureResource;
use App\Models\InfrastructureResourceShare;
use App\Services\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InfrastructureController extends Controller
{
    public function index(Request $request): View
    {
        $month = Carbon::now()->format('Y-m');
        $resources = Ledger::resources($request->user())->sortBy(fn ($resource) => ($resource->active ? '0' : '1').mb_strtolower($resource->name))->values();
        $active = $resources->where('active', true);

        return view('infrastructure.index', [
            'resources' => $resources, 'month' => $month,
            'activeCount' => $active->count(),
            'pendingCount' => $active->filter(fn ($resource) => $resource->shareFor($month) === null)->count(),
            'clientsCount' => $active->flatMap(fn ($resource) => $resource->clients())->unique('id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->infrastructureResources()->create($this->validated($request));

        return redirect()->route('infrastructure.index')->with('success', 'Recurso agregado. Cargá tu participación cuando la definas con tu socio.');
    }

    public function update(Request $request, InfrastructureResource $resource): RedirectResponse
    {
        abort_unless($resource->user_id === $request->user()->id, 404);
        $resource->update($this->validated($request, $resource));

        return redirect()->route('infrastructure.index')->with('success', 'Recurso actualizado.');
    }

    public function destroy(Request $request, InfrastructureResource $resource): RedirectResponse
    {
        abort_unless($resource->user_id === $request->user()->id, 404);
        if ($resource->inUse()) {
            return back()->withErrors(['resource' => 'El recurso está vinculado a instalaciones, bases o gastos fijos. Desvinculalo o dalo de baja.']);
        }
        $resource->delete();

        return redirect()->route('infrastructure.index')->with('success', 'Recurso eliminado.');
    }

    // Cada cambio de participación es un registro nuevo con su mes de vigencia; los meses anteriores conservan el valor que tenían.
    public function storeShare(Request $request, InfrastructureResource $resource): RedirectResponse
    {
        abort_unless($resource->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m', Rule::unique('infrastructure_resource_shares')->where('infrastructure_resource_id', $resource->id)],
            'share_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['effective_from.unique' => 'Ya hay una participación desde ese mes. Eliminala antes de cargar otra.']);
        $resource->shares()->create($data);

        return redirect()->route('infrastructure.index')->with('success', 'Participación registrada.');
    }

    public function destroyShare(Request $request, InfrastructureResource $resource, InfrastructureResourceShare $share): RedirectResponse
    {
        abort_unless($resource->user_id === $request->user()->id && $share->infrastructure_resource_id === $resource->id, 404);
        $share->delete();

        return redirect()->route('infrastructure.index')->with('success', 'Participación eliminada.');
    }

    private function validated(Request $request, ?InfrastructureResource $resource = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'provider' => ['required', Rule::in(array_keys(InfrastructureResource::PROVIDERS))],
            'kind' => ['required', Rule::in(array_keys(InfrastructureResource::KINDS))],
            'external_id' => ['nullable', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9._:-]+\z/',
                Rule::unique('infrastructure_resources')->where('user_id', $request->user()->id)->where('provider', $request->input('provider'))->ignore($resource?->id)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['external_id.unique' => 'Ya registraste ese recurso del proveedor.']);

        return [...$data, 'active' => $resource ? $request->boolean('active') : true];
    }
}
