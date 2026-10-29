<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-09-15 12:00'));
    }

    public function test_un_sobrepago_no_tapa_la_deuda_de_otro_cliente(): void
    {
        $paysTooMuch = $this->client();
        $this->client();
        $this->pay($paysTooMuch, 40000, '2026-09');

        $this->dashboard()->assertViewHas('expected', 55000)->assertViewHas('collected', 40000)->assertViewHas('pending', 27500);
    }

    public function test_los_cobros_parciales_del_mismo_mes_se_suman(): void
    {
        $client = $this->client();
        $this->pay($client, 10000, '2026-09');
        $this->pay($client, 7500, '2026-09');

        $this->dashboard()->assertViewHas('pending', 10000);
    }

    public function test_la_deuda_de_un_mes_anterior_se_mantiene_y_se_puede_cobrar_despues(): void
    {
        $client = $this->client();
        $this->dashboard();

        $this->travelTo(Carbon::parse('2026-10-05 09:00'));
        $this->dashboard()->assertViewHas('pending', 55000)->assertViewHas('arrears', 27500);

        $this->pay($client, 27500, '2026-09');
        $this->dashboard()->assertViewHas('pending', 27500)->assertViewHas('arrears', 0)->assertViewHas('collected', 0);
        $this->assertSame('2026-10-05', $client->transactions()->where('billing_month', '2026-09')->sole()->date->toDateString());
    }

    public function test_si_la_app_no_se_abre_por_meses_igual_se_generan_los_abonos(): void
    {
        $client = $this->client();
        $this->dashboard();

        $this->travelTo(Carbon::parse('2026-12-01 08:00'));
        $this->dashboard()->assertViewHas('pending', 110000)->assertViewHas('arrears', 82500);
        $this->assertSame(['2026-09', '2026-10', '2026-11', '2026-12'], $this->chargedMonths($client));
    }

    public function test_pausar_deja_de_cobrar_desde_el_mes_actual_y_conserva_la_deuda_anterior(): void
    {
        $client = $this->client();
        $this->dashboard();

        $this->travelTo(Carbon::parse('2026-10-10 10:00'));
        $this->toggle($client);
        $this->assertSame(['2026-09'], $this->chargedMonths($client));
        $this->dashboard()->assertViewHas('pending', 27500);

        $this->travelTo(Carbon::parse('2026-12-03 10:00'));
        $this->dashboard()->assertViewHas('pending', 27500);
        $this->toggle($client);
        $this->assertSame(['2026-09', '2026-12'], $this->chargedMonths($client));
        $this->dashboard()->assertViewHas('pending', 55000);
    }

    public function test_pausar_un_mes_con_pago_parcial_mantiene_el_saldo_de_ese_mes(): void
    {
        $client = $this->client();
        $this->pay($client, 10000, '2026-09');
        $this->toggle($client);

        $this->assertSame(['2026-09'], $this->chargedMonths($client));
        $this->dashboard()->assertViewHas('pending', 17500);
    }

    public function test_un_cliente_pausado_que_paga_su_deuda_no_reduce_la_de_los_demas(): void
    {
        $paused = $this->client();
        $this->client();
        $this->dashboard();
        $this->travelTo(Carbon::parse('2026-10-10 10:00'));
        $this->toggle($paused);
        $this->pay($paused, 40000, '2026-09');

        $this->dashboard()->assertViewHas('pending', 55000)->assertViewHas('arrears', 27500)->assertViewHas('collected', 0);
    }

    public function test_el_servidor_rechaza_meses_que_el_formulario_no_ofrece(): void
    {
        $active = $this->client();
        $paused = $this->client();
        $this->dashboard();
        $this->travelTo(Carbon::parse('2026-10-10 10:00'));
        $this->toggle($paused);

        $this->rejected($active, '2027-05');
        $this->rejected($active, '2026-08');
        $this->rejected($paused, '2026-10');
        $this->rejected($paused, '2026-11');
        $this->assertDatabaseCount('transactions', 0);

        $this->pay($active, 27500, '2026-11');
        $this->pay($paused, 27500, '2026-09');
    }

    public function test_cambiar_el_abono_rige_desde_el_mes_actual_sin_tocar_los_anteriores(): void
    {
        $client = $this->client();
        $this->dashboard();

        $this->travelTo(Carbon::parse('2026-10-02 10:00'));
        $this->dashboard();
        $this->actingAs($this->user)->put(route('clients.update', $client), ['name' => $client->name, 'systems' => 1, 'monthly_fee' => 30000, 'due_day' => 10])->assertRedirect();

        $this->assertSame(['2026-09' => '27500.00', '2026-10' => '30000.00'], $client->charges()->orderBy('billing_month')->pluck('amount', 'billing_month')->all());
        $this->dashboard()->assertViewHas('pending', 57500);
    }

    public function test_los_centavos_no_dejan_saldos_fantasma(): void
    {
        $client = $this->client(0.80);
        $this->pay($client, 0.70, '2026-09');
        $this->pay($client, 0.10, '2026-09');

        $this->dashboard()->assertViewHas('pending', 0);
    }

    public function test_el_formulario_ofrece_los_meses_adeudados_el_actual_y_un_adelanto(): void
    {
        $this->client();
        $this->dashboard();
        $this->travelTo(Carbon::parse('2026-10-05 09:00'));

        $this->actingAs($this->user)->get(route('clients.index'))->assertOk()
            ->assertSeeInOrder(['Septiembre 2026 · debe $27.500', 'Octubre 2026 · debe $27.500', 'Noviembre 2026 · adelanto'])
            ->assertSee('Debe septiembre 2026 ($27.500)');
    }

    public function test_no_se_puede_registrar_un_cobro_a_un_cliente_de_otro_usuario(): void
    {
        $other = User::factory()->create()->clients()->create(['name' => 'Ajeno', 'systems' => 1, 'monthly_fee' => 27500, 'due_day' => 10]);

        $this->actingAs($this->user)->post(route('clients.payment', $other), ['amount' => 100, 'billing_month' => '2026-09', 'date' => '2026-09-15'])->assertNotFound();
        $this->assertDatabaseCount('transactions', 0);
    }

    private function client(float $fee = 27500): Client
    {
        return $this->user->clients()->create(['name' => 'Cliente '.$this->user->clients()->count(), 'systems' => 1, 'monthly_fee' => $fee, 'due_day' => 10]);
    }

    private function pay(Client $client, float $amount, string $month): void
    {
        $this->actingAs($this->user)->post(route('clients.payment', $client), ['amount' => $amount, 'billing_month' => $month, 'date' => now()->toDateString()])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function rejected(Client $client, string $month): void
    {
        $this->actingAs($this->user)->post(route('clients.payment', $client), ['amount' => 100, 'billing_month' => $month, 'date' => now()->toDateString()])->assertSessionHasErrors('billing_month');
    }

    private function toggle(Client $client): void
    {
        $this->actingAs($this->user)->patch(route('clients.toggle', $client))->assertRedirect();
    }

    private function dashboard(): TestResponse
    {
        return $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
    }

    private function chargedMonths(Client $client): array
    {
        return $client->charges()->orderBy('billing_month')->pluck('billing_month')->all();
    }
}
