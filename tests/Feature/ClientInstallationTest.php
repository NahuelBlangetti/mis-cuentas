<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInstallation;
use App\Models\InstallationDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_la_base_cifrada_sin_exponer_credenciales(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $cluster = $owner->infrastructureResources()->create(['name' => 'Clúster compartido', 'provider' => 'digitalocean', 'kind' => 'database_cluster']);
        $this->actingAs($owner)->post(route('clients.installations.store', $client), $this->data(['database_resource_id' => $cluster->id]))->assertSessionHasNoErrors();

        $database = InstallationDatabase::sole();
        $this->assertSame('secreto-de-prueba', $database->connection['password']);
        $this->assertSame(3306, $database->connection['port']);
        $this->assertSame($cluster->id, $database->infrastructure_resource_id);
        $raw = DB::table('installation_databases')->first()->connection;
        $this->assertStringNotContainsString('secreto-de-prueba', $raw);
        $this->assertStringNotContainsString('database.example.com', $raw);
        $this->assertArrayNotHasKey('connection', $database->toArray());
        $this->assertArrayNotHasKey('connection', ClientInstallation::with('databases')->first()->toArray()['databases'][0]);
        $this->get(route('clients.installations.index', $client))->assertOk()
            ->assertDontSee('secreto-de-prueba')->assertDontSee('lector_prueba')->assertDontSee('database.example.com')->assertSee('Clúster compartido');
    }

    public function test_la_base_es_opcional_y_una_instalacion_admite_varias(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $this->actingAs($owner)->post(route('clients.installations.store', $client), ['name' => 'Sistema', 'url' => 'https://sistema.example.com', 'port' => 3306])->assertSessionHasNoErrors();
        $installation = ClientInstallation::sole();
        $this->assertSame(0, $installation->databases()->count());

        foreach (['Sucursal uno', 'Sucursal dos'] as $name) {
            $this->post(route('clients.installations.databases.store', [$client, $installation]), $this->connection(['name' => $name]))->assertSessionHasNoErrors();
        }
        $this->assertSame(['Sucursal dos', 'Sucursal uno'], $installation->databases()->orderBy('name')->pluck('name')->all());
    }

    public function test_una_conexion_incompleta_no_se_guarda(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $this->actingAs($owner)->post(route('clients.installations.store', $client), ['name' => 'Sistema', 'url' => 'https://sistema.example.com', 'port' => 3306, 'password' => 'solo-la-clave'])
            ->assertSessionHasErrors(['host', 'database', 'username']);
        $this->assertDatabaseCount('client_installations', 0);
        $this->assertDatabaseCount('installation_databases', 0);
    }

    public function test_editar_una_base_conserva_o_reemplaza_las_credenciales(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $this->actingAs($owner)->post(route('clients.installations.store', $client), $this->data())->assertSessionHasNoErrors();
        $installation = ClientInstallation::sole();
        $database = $installation->databases()->sole();

        $this->put(route('clients.installations.databases.update', [$client, $installation, $database]), ['name' => 'Renombrada', 'port' => 3306])->assertSessionHasNoErrors();
        $database->refresh();
        $this->assertSame('Renombrada', $database->name);
        $this->assertSame('secreto-de-prueba', $database->connection['password']);

        $this->put(route('clients.installations.databases.update', [$client, $installation, $database]), $this->connection(['name' => 'Renombrada', 'password' => 'clave-nueva']))->assertSessionHasNoErrors();
        $this->assertSame('clave-nueva', $database->refresh()->connection['password']);
    }

    public function test_no_permite_vincular_recursos_de_otro_usuario(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $foreign = User::factory()->create()->infrastructureResources()->create(['name' => 'Ajeno', 'provider' => 'other', 'kind' => 'server']);

        $this->actingAs($owner)->post(route('clients.installations.store', $client), $this->data(['infrastructure_resource_id' => $foreign->id]))->assertSessionHasErrors('infrastructure_resource_id');
        $this->post(route('clients.installations.store', $client), $this->data(['database_resource_id' => $foreign->id]))->assertSessionHasErrors('database_resource_id');
        $this->assertDatabaseCount('client_installations', 0);

        $installation = $client->installations()->create(['name' => 'Sistema', 'url' => 'https://example.com']);
        $database = $installation->databases()->create(['name' => 'Principal', 'connection' => ['host' => 'h']]);
        $this->put(route('clients.installations.update', [$client, $installation]), ['name' => 'Sistema', 'url' => 'https://example.com', 'infrastructure_resource_id' => $foreign->id])->assertSessionHasErrors('infrastructure_resource_id');
        $this->put(route('clients.installations.databases.update', [$client, $installation, $database]), ['name' => 'Principal', 'infrastructure_resource_id' => $foreign->id])->assertSessionHasErrors('infrastructure_resource_id');
        $this->assertNull($installation->refresh()->infrastructure_resource_id);
        $this->assertNull($database->refresh()->infrastructure_resource_id);
    }

    public function test_rechaza_acceso_a_las_instalaciones_de_otro_usuario(): void
    {
        [, $client] = $this->ownerWithClient();
        $other = User::factory()->create();
        $installation = $client->installations()->create(['name' => 'Sistema', 'url' => 'https://example.com']);
        $database = $installation->databases()->create(['name' => 'Principal', 'connection' => $this->connection()]);

        $this->actingAs($other)->get(route('clients.installations.index', $client))->assertNotFound();
        $this->post(route('clients.installations.store', $client), $this->data())->assertNotFound();
        $this->put(route('clients.installations.update', [$client, $installation]), ['name' => 'X', 'url' => 'https://x.example.com'])->assertNotFound();
        $this->post(route('clients.installations.databases.store', [$client, $installation]), $this->connection())->assertNotFound();
        $this->put(route('clients.installations.databases.update', [$client, $installation, $database]), $this->connection())->assertNotFound();
        $this->delete(route('clients.installations.databases.destroy', [$client, $installation, $database]))->assertNotFound();
        $this->delete(route('clients.installations.destroy', [$client, $installation]))->assertNotFound();
        $this->assertModelExists($installation);
        $this->assertModelExists($database);
        $this->assertSame('Sistema', $installation->refresh()->name);
    }

    public function test_no_mezcla_bases_entre_instalaciones_del_mismo_usuario(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $first = $client->installations()->create(['name' => 'Uno', 'url' => 'https://uno.example.com']);
        $second = $client->installations()->create(['name' => 'Dos', 'url' => 'https://dos.example.com']);
        $database = $first->databases()->create(['name' => 'Principal', 'connection' => $this->connection()]);

        $this->actingAs($owner)->delete(route('clients.installations.databases.destroy', [$client, $second, $database]))->assertNotFound();
        $this->assertModelExists($database);
    }

    public function test_eliminar_la_instalacion_elimina_sus_bases(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $installation = $client->installations()->create(['name' => 'Sistema', 'url' => 'https://example.com']);
        $installation->databases()->create(['name' => 'Principal', 'connection' => $this->connection()]);

        $this->actingAs($owner)->delete(route('clients.installations.destroy', [$client, $installation]))->assertRedirect();
        $this->assertDatabaseCount('installation_databases', 0);
    }

    public function test_validacion_no_guarda_ni_flasha_la_clave(): void
    {
        [$owner, $client] = $this->ownerWithClient();
        $this->actingAs($owner)->post(route('clients.installations.store', $client), $this->data(['url' => 'http://example.com']))
            ->assertSessionHasErrors('url')->assertSessionMissing('_old_input.password');
        $this->assertDatabaseCount('client_installations', 0);
        $this->post(route('clients.installations.store', $client), $this->data(['url' => 'https://user:secret@example.com']))->assertSessionHasErrors('url');
        $this->assertDatabaseCount('client_installations', 0);
        $this->assertDatabaseCount('installation_databases', 0);
    }

    private function ownerWithClient(): array
    {
        $owner = User::factory()->create();

        return [$owner, $owner->clients()->create(['name' => 'Cliente', 'systems' => 2, 'monthly_fee' => 100, 'due_day' => 10])];
    }

    private function connection(array $overrides = []): array
    {
        return [...[
            'name' => 'Principal', 'host' => 'database.example.com', 'port' => 3306, 'database' => 'production',
            'username' => 'lector_prueba', 'password' => 'secreto-de-prueba',
        ], ...$overrides];
    }

    private function data(array $overrides = []): array
    {
        return [...$this->connection(), 'name' => 'Sistema', 'url' => 'https://sistema.example.com', ...$overrides];
    }
}
