<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInstallation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientInstallationController extends Controller
{
    public function index(Request $request, Client $client): View
    {
        abort_unless($client->user_id === $request->user()->id, 404);

        return view('clients.installations', ['client' => $client, 'installations' => $client->installations()->orderBy('name')->get()]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'url' => ['required', 'url:https', 'max:2048', function ($attribute, $value, $fail) {
                $parts = parse_url($value);
                if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                    $fail('Usá la URL del sistema sin credenciales, parámetros ni fragmentos.');
                }
            }],
            'host' => ['required', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9.:-]+\z/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ]);

        $client->installations()->create([
            'name' => $data['name'], 'url' => $data['url'],
            'connection' => array_intersect_key($data, array_flip(['host', 'port', 'database', 'username', 'password'])),
        ]);

        return redirect()->route('clients.installations.index', $client)->with('success', 'Instalación guardada. La conexión todavía no fue verificada.');
    }

    public function destroy(Request $request, Client $client, ClientInstallation $installation): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id && $installation->client_id === $client->id, 404);
        $installation->delete();

        return redirect()->route('clients.installations.index', $client)->with('success', 'Instalación eliminada.');
    }
}
