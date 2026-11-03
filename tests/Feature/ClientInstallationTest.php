<?php

namespace Tests\Feature;

use App\Models\ClientInstallation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_varias_bases_cifradas_sin_exponer_credenciales(): void
    {
        $owner = User::factory()->create();
        $client = $owner->clients()->create(['name' => 'Cliente', 'systems' => 2, 'monthly_fee' => 100, 'due_day' => 10]);
        foreach (['Sucursal uno', 'Sucursal dos'] as $name) {
            $this->actingAs($owner)->post(route('clients.installations.store', $client), $this->data(['name' => $name]))->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $client->installations()->count());
        $installation = ClientInstallation::first();
        $this->assertSame('secreto-de-prueba', $installation->connection['password']);
        $raw = DB::table('client_installations')->first()->connection;
        $this->assertStringNotContainsString('secreto-de-prueba', $raw);
        $this->assertStringNotContainsString('database.example.com', $raw);
        $this->assertArrayNotHasKey('connection', $installation->toArray());
        $this->get(route('clients.installations.index', $client))->assertOk()->assertDontSee('secreto-de-prueba')->assertDontSee('lector_prueba');
    }

    public function test_rechaza_acceso_a_las_instalaciones_de_otro_usuario(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $client = $owner->clients()->create(['name' => 'Cliente', 'systems' => 1, 'monthly_fee' => 100, 'due_day' => 10]);
        $installation = $client->installations()->create(['name' => 'Sistema', 'url' => 'https://example.com', 'connection' => $this->data()]);
        $this->actingAs($other)->get(route('clients.installations.index', $client))->assertNotFound();
        $this->post(route('clients.installations.store', $client), $this->data())->assertNotFound();
        $this->delete(route('clients.installations.destroy', [$client, $installation]))->assertNotFound();
        $this->assertModelExists($installation);
    }

    public function test_validacion_no_guarda_ni_flasha_la_clave(): void
    {
        $owner = User::factory()->create();
        $client = $owner->clients()->create(['name' => 'Cliente', 'systems' => 1, 'monthly_fee' => 100, 'due_day' => 10]);
        $this->actingAs($owner)->post(route('clients.installations.store', $client), $this->data(['url' => 'http://example.com']))
            ->assertSessionHasErrors('url')->assertSessionMissing('_old_input.password');
        $this->assertDatabaseCount('client_installations', 0);
        $this->post(route('clients.installations.store', $client), $this->data(['url' => 'https://user:secret@example.com']))->assertSessionHasErrors('url');
        $this->assertDatabaseCount('client_installations', 0);
    }

    private function data(array $overrides = []): array
    {
        return [...[
            'name' => 'Sistema', 'url' => 'https://sistema.example.com',
            'host' => 'database.example.com', 'port' => 3306, 'database' => 'production',
            'username' => 'lector_prueba', 'password' => 'secreto-de-prueba',
        ], ...$overrides];
    }
}
