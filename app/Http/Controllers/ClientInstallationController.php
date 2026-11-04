<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInstallation;
use App\Models\InstallationDatabase;
use App\Services\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientInstallationController extends Controller
{
    public function index(Request $request, Client $client): View
    {
        abort_unless($client->user_id === $request->user()->id, 404);

        return view('clients.installations', [
            'client' => $client,
            'installations' => Ledger::installations($request->user())->where('client_id', $client->id)->sortBy(fn ($installation) => mb_strtolower($installation->name))->values(),
            'resources' => Ledger::resources($request->user())->sortBy(fn ($resource) => ($resource->active ? '0' : '1').mb_strtolower($resource->name))->values(),
        ]);
    }

    // La primera base es opcional: puede cargarse después, cuando exista el usuario de solo lectura.
    public function store(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $data = $request->validate([...self::rules($request), ...InstallationDatabase::connectionRules(optional: true), 'database_resource_id' => self::resourceRule($request)]);

        DB::transaction(function () use ($client, $data) {
            $installation = $client->installations()->create(['name' => $data['name'], 'url' => $data['url'], 'infrastructure_resource_id' => $data['infrastructure_resource_id'] ?? null]);
            if ($connection = InstallationDatabase::connectionFrom($data)) {
                $installation->databases()->create(['name' => 'Principal', 'infrastructure_resource_id' => $data['database_resource_id'] ?? null, 'connection' => $connection]);
            }
        });

        return redirect()->route('clients.installations.index', $client)->with('success', 'Instalación guardada. La conexión todavía no fue verificada.');
    }

    public function update(Request $request, Client $client, ClientInstallation $installation): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id && $installation->client_id === $client->id, 404);
        $installation->update($request->validate(self::rules($request)));

        return redirect()->route('clients.installations.index', $client)->with('success', 'Instalación actualizada.');
    }

    public function destroy(Request $request, Client $client, ClientInstallation $installation): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id && $installation->client_id === $client->id, 404);
        $installation->delete();

        return redirect()->route('clients.installations.index', $client)->with('success', 'Instalación eliminada junto con sus bases y credenciales.');
    }

    private static function rules(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'url' => ['required', 'url:https', 'max:2048', function ($attribute, $value, $fail) {
                $parts = parse_url($value);
                if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                    $fail('Usá la URL del sistema sin credenciales, parámetros ni fragmentos.');
                }
            }],
            'infrastructure_resource_id' => self::resourceRule($request),
        ];
    }

    // Solo se puede vincular un recurso propio: un id ajeno no revela si existe.
    public static function resourceRule(Request $request): array
    {
        return ['nullable', 'integer', Rule::exists('infrastructure_resources', 'id')->where('user_id', $request->user()->id)];
    }
}
