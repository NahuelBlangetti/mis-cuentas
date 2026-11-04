<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInstallation;
use App\Models\InstallationDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstallationDatabaseController extends Controller
{
    public function store(Request $request, Client $client, ClientInstallation $installation): RedirectResponse
    {
        $this->authorizeInstallation($request, $client, $installation);
        $data = $request->validate([...$this->rules($request), ...InstallationDatabase::connectionRules()]);
        $installation->databases()->create(['name' => $data['name'], 'infrastructure_resource_id' => $data['infrastructure_resource_id'] ?? null, 'connection' => InstallationDatabase::connectionFrom($data)]);

        return redirect()->route('clients.installations.index', $client)->with('success', 'Base agregada. La conexión todavía no fue verificada.');
    }

    // Las credenciales guardadas nunca se muestran: solo se reemplazan si se cargan todas de nuevo.
    public function update(Request $request, Client $client, ClientInstallation $installation, InstallationDatabase $database): RedirectResponse
    {
        $this->authorizeDatabase($request, $client, $installation, $database);
        $data = $request->validate([...$this->rules($request), ...InstallationDatabase::connectionRules(optional: true)]);
        $connection = InstallationDatabase::connectionFrom($data);
        $database->update(['name' => $data['name'], 'infrastructure_resource_id' => $data['infrastructure_resource_id'] ?? null, ...($connection ? ['connection' => $connection] : [])]);

        return redirect()->route('clients.installations.index', $client)->with('success', $connection ? 'Base actualizada con credenciales nuevas.' : 'Base actualizada. Las credenciales no cambiaron.');
    }

    public function destroy(Request $request, Client $client, ClientInstallation $installation, InstallationDatabase $database): RedirectResponse
    {
        $this->authorizeDatabase($request, $client, $installation, $database);
        $database->delete();

        return redirect()->route('clients.installations.index', $client)->with('success', 'Base y credenciales eliminadas.');
    }

    private function rules(Request $request): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'infrastructure_resource_id' => ClientInstallationController::resourceRule($request)];
    }

    private function authorizeInstallation(Request $request, Client $client, ClientInstallation $installation): void
    {
        abort_unless($client->user_id === $request->user()->id && $installation->client_id === $client->id, 404);
    }

    private function authorizeDatabase(Request $request, Client $client, ClientInstallation $installation, InstallationDatabase $database): void
    {
        $this->authorizeInstallation($request, $client, $installation);
        abort_unless($database->client_installation_id === $installation->id, 404);
    }
}
