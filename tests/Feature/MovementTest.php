<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MovementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-10-15 12:00'));
    }

    public function test_el_csv_neutraliza_celdas_que_se_abrirían_como_fórmulas(): void
    {
        $this->movement(['description' => '=HYPERLINK("http://x","clic")', 'category' => '@SUM(A1)']);
        $this->movement(['description' => '-2+3', 'category' => '+cmd']);
        $this->movement(['description' => 'Internet "fibra"', 'category' => null]);
        // Los formularios recortan espacios, pero un dato cargado por otra vía podría traerlos.
        $this->movement(['description' => '  =1+1', 'category' => "\n=1+1"]);

        $csv = $this->actingAs($this->user)->get(route('movements.export'))->assertOk()->getContent();

        $this->assertStringContainsString('"\'=HYPERLINK(""http://x"",""clic"")";"\'@SUM(A1)"', $csv);
        $this->assertStringContainsString('"\'-2+3";"\'+cmd"', $csv);
        $this->assertStringContainsString("\"'  =1+1\";\"'\n=1+1\"", $csv);
        $this->assertStringContainsString('"Internet ""fibra""";""', $csv);
        $this->assertDoesNotMatchRegularExpression('/;"[=+\-@]/', $csv);
    }

    public function test_se_puede_editar_un_movimiento(): void
    {
        $movement = $this->movement();

        $this->actingAs($this->user)->put(route('movements.update', $movement), [
            'type' => 'income', 'area' => 'personal', 'description' => 'Venta usada', 'category' => 'Ventas', 'amount' => 1500.5, 'date' => '2026-10-02',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $movement->refresh();
        $this->assertSame(['income', 'personal', 'Venta usada', 'Ventas', '1500.50', '2026-10-02'], [$movement->type, $movement->area, $movement->description, $movement->category, $movement->amount, $movement->date->toDateString()]);
    }

    public function test_editar_un_cobro_de_cliente_no_cambia_su_tipo_ni_su_mes(): void
    {
        $client = $this->user->clients()->create(['name' => 'Cliente', 'systems' => 1, 'monthly_fee' => 27500, 'due_day' => 10]);
        $payment = $this->user->transactions()->create(['client_id' => $client->id, 'type' => 'income', 'area' => 'business', 'description' => 'Abono', 'amount' => 27500, 'date' => '2026-10-01', 'billing_month' => '2026-09']);

        $this->actingAs($this->user)->put(route('movements.update', $payment), [
            'type' => 'expense', 'area' => 'personal', 'description' => 'Abono septiembre', 'amount' => 20000, 'date' => '2026-10-03',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame(['income', 'business', '2026-09', 'Abono septiembre', '20000.00'], [$payment->type, $payment->area, $payment->billing_month, $payment->description, $payment->amount]);
    }

    public function test_un_movimiento_puede_ser_de_un_cliente_ocasional(): void
    {
        $this->actingAs($this->user)->post(route('movements.store'), [
            'type' => 'income', 'area' => 'business', 'client' => 'occasional', 'client_name' => 'Kiosco Centro', 'description' => 'Instalación', 'amount' => 50000, 'date' => '2026-10-05',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $movement = Transaction::sole();
        $this->assertSame([null, 'Kiosco Centro', null, 'Kiosco Centro (ocasional)'], [$movement->client_id, $movement->client_name, $movement->billing_month, $movement->clientLabel()]);
        $this->actingAs($this->user)->get(route('movements.index'))->assertSee('Kiosco Centro (ocasional)');
        $this->assertStringContainsString('"Kiosco Centro (ocasional)"', $this->actingAs($this->user)->get(route('movements.export'))->getContent());

        $this->actingAs($this->user)->post(route('movements.store'), [
            'type' => 'income', 'area' => 'business', 'client' => 'occasional', 'description' => 'Sin nombre', 'amount' => 1, 'date' => '2026-10-05',
        ])->assertSessionHasErrors('client_name');
    }

    public function test_un_trabajo_extra_de_un_cliente_registrado_no_salda_su_abono(): void
    {
        $client = $this->user->clients()->create(['name' => 'Cliente', 'systems' => 1, 'monthly_fee' => 27500, 'due_day' => 10]);

        $this->actingAs($this->user)->post(route('movements.store'), [
            'type' => 'income', 'area' => 'business', 'client' => (string) $client->id, 'client_name' => 'Ignorado', 'description' => 'Reporte a medida', 'amount' => 30000, 'date' => '2026-10-05',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $movement = Transaction::sole();
        $this->assertSame([$client->id, null, null], [$movement->client_id, $movement->client_name, $movement->billing_month]);
        $this->actingAs($this->user)->get(route('clients.index'))->assertOk();
        $this->assertEquals(27500, \App\Services\Billing::balances($this->user, '2026-10')[$client->id]['total']);
    }

    public function test_no_se_puede_asignar_un_cliente_ajeno_y_lo_personal_no_lleva_cliente(): void
    {
        $other = User::factory()->create()->clients()->create(['name' => 'Ajeno', 'systems' => 1, 'monthly_fee' => 1000, 'due_day' => 10]);
        $mine = $this->user->clients()->create(['name' => 'Propio', 'systems' => 1, 'monthly_fee' => 1000, 'due_day' => 10]);

        $this->actingAs($this->user)->post(route('movements.store'), [
            'type' => 'income', 'area' => 'business', 'client' => (string) $other->id, 'description' => 'Intento', 'amount' => 1, 'date' => '2026-10-05',
        ])->assertSessionHasErrors('client');
        $this->actingAs($this->user)->post(route('movements.store'), [
            'type' => 'expense', 'area' => 'personal', 'client' => (string) $mine->id, 'description' => 'Super', 'amount' => 1, 'date' => '2026-10-05',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Transaction::sole()->client_id);
    }

    public function test_se_puede_cambiar_el_cliente_de_un_trabajo_extra(): void
    {
        $client = $this->user->clients()->create(['name' => 'Cliente', 'systems' => 1, 'monthly_fee' => 1000, 'due_day' => 10]);
        $movement = $this->movement(['type' => 'income', 'client_id' => $client->id, 'description' => 'Extra']);

        $this->actingAs($this->user)->put(route('movements.update', $movement), [
            'type' => 'income', 'area' => 'business', 'client' => 'occasional', 'client_name' => 'Vecino', 'description' => 'Extra', 'amount' => 5000, 'date' => '2026-10-02',
        ])->assertSessionHasNoErrors();

        $movement->refresh();
        $this->assertSame([null, 'Vecino'], [$movement->client_id, $movement->client_name]);
    }

    public function test_no_se_puede_editar_ni_borrar_un_movimiento_de_otro_usuario(): void
    {
        $other = User::factory()->create()->transactions()->create(['type' => 'expense', 'area' => 'business', 'description' => 'Ajeno', 'amount' => 100, 'date' => '2026-10-01']);

        $this->actingAs($this->user)->put(route('movements.update', $other), ['type' => 'expense', 'area' => 'business', 'description' => 'Cambiado', 'amount' => 1, 'date' => '2026-10-01'])->assertNotFound();
        $this->actingAs($this->user)->delete(route('movements.destroy', $other))->assertNotFound();
        $this->assertSame('Ajeno', $other->fresh()->description);
    }

    public function test_la_lista_se_pagina_sin_perder_el_filtro(): void
    {
        foreach (range(1, 55) as $day) {
            $this->movement(['description' => "Gasto $day", 'date' => Carbon::parse('2026-01-01')->addDays($day)->toDateString()]);
        }
        $this->movement(['area' => 'personal', 'description' => 'Personal']);

        $this->actingAs($this->user)->get(route('movements.index', ['area' => 'business']))->assertOk()
            ->assertSee('55 registros')->assertSee('Página 1 de 2')->assertSee('area=business&amp;page=2', false)->assertDontSee('Gasto 5<', false);
        $this->actingAs($this->user)->get(route('movements.index', ['area' => 'business', 'page' => 2]))->assertOk()
            ->assertSee('Gasto 5<', false)->assertDontSee('Personal</b>', false);
    }

    public function test_finanzas_personales_navega_por_mes(): void
    {
        $this->movement(['area' => 'personal', 'type' => 'income', 'description' => 'Sueldo de septiembre', 'amount' => 900000, 'date' => '2026-09-05']);
        $this->movement(['area' => 'personal', 'description' => 'Super de octubre', 'amount' => 50000, 'date' => '2026-10-03']);

        $this->actingAs($this->user)->get(route('personal.index'))->assertOk()
            ->assertSee('Super de octubre')->assertDontSee('Sueldo de septiembre')->assertSee('mes=2026-09')->assertDontSee('mes=2026-11');
        $this->actingAs($this->user)->get(route('personal.index', ['mes' => '2026-09']))->assertOk()
            ->assertSee('Sueldo de septiembre')->assertDontSee('Super de octubre')->assertViewHas('income', 900000)->assertSee('mes=2026-10');
        $this->actingAs($this->user)->get(route('personal.index', ['mes' => '2026-13']))->assertOk()->assertSee('Super de octubre');
    }

    private function movement(array $attributes = []): Transaction
    {
        return $this->user->transactions()->create([
            'type' => 'expense', 'area' => 'business', 'description' => 'Internet', 'category' => 'Servicios', 'amount' => 12000, 'date' => '2026-10-01', ...$attributes,
        ]);
    }
}
