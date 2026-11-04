<?php

namespace Tests\Feature;

use App\Models\InfrastructureResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cluster_compartido_se_registra_una_sola_vez_para_varios_clientes(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('infrastructure.store'), ['name' => 'Clúster MySQL', 'provider' => 'digitalocean', 'kind' => 'database_cluster', 'external_id' => 'a1b2-c3'])->assertSessionHasNoErrors();
        $cluster = InfrastructureResource::sole();

        foreach (['Ferretería', 'Carnicería'] as $name) {
            $client = $owner->clients()->create(['name' => $name, 'systems' => 1, 'monthly_fee' => 100, 'due_day' => 10]);
            $installation = $client->installations()->create(['name' => 'Sistema', 'url' => 'https://example.com']);
            $installation->databases()->create(['name' => 'Principal', 'infrastructure_resource_id' => $cluster->id, 'connection' => ['host' => 'h']]);
        }

        $this->assertDatabaseCount('infrastructure_resources', 1);
        $this->assertSame(['Carnicería', 'Ferretería'], $cluster->fresh()->load('installations.client', 'databases.installation.client')->clients()->pluck('name')->all());
        $this->get(route('infrastructure.index'))->assertOk()->assertSee('Clientes: Carnicería, Ferretería')->assertSee('0 inst. · 2 bases');
    }

    public function test_sin_participacion_cargada_queda_pendiente_y_no_asume_un_reparto(): void
    {
        $owner = User::factory()->create();
        $resource = $this->resource($owner);

        $this->assertNull($resource->shareFor(Carbon::now()->format('Y-m')));
        $this->actingAs($owner)->get(route('infrastructure.index'))->assertOk()->assertSee('Pendiente')->assertDontSee('50 %');
    }

    public function test_la_participacion_tiene_vigencia_y_no_reescribe_meses_anteriores(): void
    {
        $owner = User::factory()->create();
        $resource = $this->resource($owner);
        $this->actingAs($owner)->post(route('infrastructure.shares.store', $resource), ['effective_from' => '2026-01', 'share_percent' => '40'])->assertSessionHasNoErrors();
        $this->post(route('infrastructure.shares.store', $resource), ['effective_from' => '2026-06', 'share_percent' => '37.5', 'notes' => 'Nuevo acuerdo'])->assertSessionHasNoErrors();
        $this->post(route('infrastructure.shares.store', $resource), ['effective_from' => '2026-06', 'share_percent' => '90'])->assertSessionHasErrors('effective_from');

        $resource->load('shares');
        $this->assertNull($resource->shareFor('2025-12'));
        $this->assertSame('40.00', $resource->shareFor('2026-03')->share_percent);
        $this->assertSame('37.50', $resource->shareFor('2026-06')->share_percent);
        $this->assertSame('37,5 %', $resource->shareFor('2026-11')->percentLabel());
        $this->assertSame('40 %', $resource->shareFor('2026-05')->percentLabel());
    }

    public function test_valida_porcentaje_y_mes(): void
    {
        $owner = User::factory()->create();
        $resource = $this->resource($owner);
        $this->actingAs($owner)->post(route('infrastructure.shares.store', $resource), ['effective_from' => '2026-01', 'share_percent' => '100.5'])->assertSessionHasErrors('share_percent');
        $this->post(route('infrastructure.shares.store', $resource), ['effective_from' => '2026-01', 'share_percent' => '33.333'])->assertSessionHasErrors('share_percent');
        $this->post(route('infrastructure.shares.store', $resource), ['effective_from' => '2026-13', 'share_percent' => '40'])->assertSessionHasErrors('effective_from');
        $this->post(route('infrastructure.store'), ['name' => 'X', 'provider' => 'inventado', 'kind' => 'server'])->assertSessionHasErrors('provider');
        $this->assertDatabaseCount('infrastructure_resource_shares', 0);
    }

    public function test_el_id_del_proveedor_no_se_repite_para_el_mismo_usuario(): void
    {
        $owner = User::factory()->create();
        $data = ['name' => 'Clúster', 'provider' => 'digitalocean', 'kind' => 'database_cluster', 'external_id' => 'uuid-1'];
        $this->actingAs($owner)->post(route('infrastructure.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('infrastructure.store'), $data)->assertSessionHasErrors('external_id');

        $resource = InfrastructureResource::sole();
        $this->put(route('infrastructure.update', $resource), [...$data, 'name' => 'Renombrado', 'active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Renombrado', $resource->refresh()->name);

        $this->actingAs(User::factory()->create())->post(route('infrastructure.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('infrastructure_resources', 2);
    }

    public function test_dar_de_baja_y_no_eliminar_un_recurso_en_uso(): void
    {
        $owner = User::factory()->create();
        $resource = $this->resource($owner);
        $client = $owner->clients()->create(['name' => 'Cliente', 'systems' => 1, 'monthly_fee' => 100, 'due_day' => 10]);
        $installation = $client->installations()->create(['name' => 'Sistema', 'url' => 'https://example.com', 'infrastructure_resource_id' => $resource->id]);

        $this->actingAs($owner)->delete(route('infrastructure.destroy', $resource))->assertSessionHasErrors('resource');
        $this->assertModelExists($resource);

        $this->put(route('infrastructure.update', $resource), ['name' => $resource->name, 'provider' => 'other', 'kind' => 'server', 'active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($resource->refresh()->active);
        $this->assertSame($resource->id, $installation->refresh()->infrastructure_resource_id);

        $installation->update(['infrastructure_resource_id' => null]);
        $this->delete(route('infrastructure.destroy', $resource))->assertSessionHasNoErrors();
        $this->assertModelMissing($resource);
    }

    public function test_aisla_los_recursos_entre_usuarios(): void
    {
        $owner = User::factory()->create();
        $resource = $this->resource($owner, 'Servidor privado del dueño');
        $share = $resource->shares()->create(['effective_from' => '2026-01', 'share_percent' => 40]);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('infrastructure.index'))->assertOk()->assertDontSee('Servidor privado del dueño');
        $this->put(route('infrastructure.update', $resource), ['name' => 'Tomado', 'provider' => 'other', 'kind' => 'server'])->assertNotFound();
        $this->delete(route('infrastructure.destroy', $resource))->assertNotFound();
        $this->post(route('infrastructure.shares.store', $resource), ['effective_from' => '2026-02', 'share_percent' => '10'])->assertNotFound();
        $this->delete(route('infrastructure.shares.destroy', [$resource, $share]))->assertNotFound();

        $otherResource = $this->resource($other);
        $this->delete(route('infrastructure.shares.destroy', [$otherResource, $share]))->assertNotFound();
        $this->assertModelExists($share);
        $this->assertSame('Servidor privado del dueño', $resource->refresh()->name);
    }

    private function resource(User $user, string $name = 'Servidor'): InfrastructureResource
    {
        return $user->infrastructureResources()->create(['name' => $name, 'provider' => 'other', 'kind' => 'server']);
    }
}
