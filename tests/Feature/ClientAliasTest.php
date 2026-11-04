<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClientAliasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-11-04 12:00'));
    }

    public function test_el_alias_es_opcional_y_se_puede_editar_o_quitar(): void
    {
        $this->actingAs($this->user)->post(route('clients.store'), $this->data())->assertSessionHasNoErrors();
        $client = Client::sole();
        $this->assertNull($client->alias);
        $this->assertSame('Juan Pérez', $client->label());

        $this->actingAs($this->user)->put(route('clients.update', $client), $this->data(['alias' => '  Black Point  ']))->assertSessionHasNoErrors();
        $client->refresh();
        $this->assertSame('Black Point', $client->alias);
        $this->assertSame('Juan Pérez · Black Point', $client->label());

        $this->actingAs($this->user)->put(route('clients.update', $client), $this->data(['alias' => '']))->assertSessionHasNoErrors();
        $this->assertNull($client->fresh()->alias);
    }

    public function test_un_alias_demasiado_largo_no_se_guarda(): void
    {
        $this->actingAs($this->user)->post(route('clients.store'), $this->data(['alias' => str_repeat('a', 81)]))->assertSessionHasErrors('alias');
        $this->assertSame(0, Client::count());
    }

    public function test_el_alias_se_ve_en_las_listas_pero_no_en_el_mensaje_al_cliente(): void
    {
        $client = $this->user->clients()->create($this->data(['alias' => 'Ferretería El Gringo', 'phone' => '5493511234567']));

        $this->actingAs($this->user)->get(route('clients.index'))->assertOk()->assertSee('Ferretería El Gringo')->assertSee('data-alias="Ferretería El Gringo"', false);
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('Juan Pérez · Ferretería El Gringo');
        $this->actingAs($this->user)->get(route('movements.index'))->assertOk()->assertSee('Juan Pérez · Ferretería El Gringo');
        $this->actingAs($this->user)->get(route('clients.installations.index', $client))->assertOk()->assertSee('Juan Pérez · Ferretería El Gringo');

        $message = WhatsApp::chargeMessage($client, ['2026-11' => 27500]);
        $this->assertStringContainsString('¡Hola Juan Pérez!', $message);
        $this->assertStringNotContainsString('Gringo', $message);
    }

    public function test_el_buscador_aparece_desde_diez_clientes(): void
    {
        foreach (range(1, 9) as $number) $this->user->clients()->create($this->data(['name' => "Cliente $number"]));
        $this->actingAs($this->user)->get(route('clients.index'))->assertOk()->assertDontSee('data-client-search', false);

        $this->user->clients()->create($this->data(['name' => 'José Núñez', 'alias' => 'Punto Manija', 'phone' => '5493511234567']));
        $this->actingAs($this->user)->get(route('clients.index'))->assertOk()
            ->assertSee('data-client-search', false)->assertSee('data-client-search-empty', false)
            ->assertSee('data-search="José Núñez Punto Manija" data-search-phone="5493511234567"', false);
    }

    private function data(array $attributes = []): array
    {
        return ['name' => 'Juan Pérez', 'systems' => 1, 'monthly_fee' => 27500, 'due_day' => 10, ...$attributes];
    }
}
