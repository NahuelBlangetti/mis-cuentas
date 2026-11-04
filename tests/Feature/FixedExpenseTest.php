<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\FixedExpense;
use App\Models\User;
use App\Services\FixedExpenses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FixedExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 11, 15, 12));
        Http::preventStrayRequests();
    }

    public function test_pasa_las_suscripciones_en_dolares_a_pesos_con_la_cotizacion_del_mes(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('fixed-expenses.store'), ['name' => 'Anthropic', 'category' => 'tools', 'currency' => 'USD', 'amount' => '20', 'effective_from' => '2026-11'])
            ->assertSessionHasNoErrors()->assertRedirect(route('fixed-expenses.index', ['mes' => '2026-11']));
        $this->expense($owner, 'Cursor', ['2026-11' => 20]);
        $this->expense($owner, 'ChatGPT', ['2026-11' => 20]);
        FixedExpenses::storeRate($owner, '2026-11', 1668.81, 'arq');

        $report = FixedExpenses::report($owner, '2026-11');
        $this->assertSame(100128.6, $report['totalArs']);
        $this->assertSame(60.0, $report['totalUsd']);
        $this->assertSame(3, $report['activeCount']);
        $this->get(route('fixed-expenses.index'))->assertOk()->assertSee('$100.128,60')->assertSee('$33.376,20')->assertSee('60 USD')->assertSee('$1.668,81');
    }

    public function test_un_cambio_de_plan_rige_desde_su_mes_y_los_meses_anteriores_conservan_importe_y_cotizacion(): void
    {
        $owner = User::factory()->create();
        $cursor = $this->expense($owner, 'Cursor', ['2026-10' => 60]);
        FixedExpenses::storeRate($owner, '2026-10', 1500, 'manual');
        FixedExpenses::storeRate($owner, '2026-11', 1668.81, 'arq');

        $this->actingAs($owner)->post(route('fixed-expenses.amounts.store', $cursor), ['effective_from' => '2026-11', 'amount' => '20'])->assertSessionHasNoErrors();
        $this->post(route('fixed-expenses.amounts.store', $cursor), ['effective_from' => '2026-11', 'amount' => '30'])->assertSessionHasErrors('effective_from');
        FixedExpenses::storeRate($owner, '2026-11', 1700, 'manual');

        $this->assertSame(90000.0, FixedExpenses::report($owner, '2026-10')['totalArs']);
        $this->assertSame(34000.0, FixedExpenses::report($owner, '2026-11')['totalArs']);
        $this->get(route('fixed-expenses.index', ['mes' => '2026-10']))->assertOk()->assertSee('$90.000')->assertSee('60 USD')->assertDontSee('Actualizar con Arq');
        $this->get(route('fixed-expenses.index', ['mes' => '2026-09']))->assertOk()->assertSee('Desde octubre 2026');

        // Un importe 0 lo da de baja desde ese mes, sin tocar los anteriores ni pedir cotización.
        $cursor->amounts()->create(['effective_from' => '2026-12', 'amount' => 0]);
        $december = FixedExpenses::report($owner, '2026-12');
        $this->assertSame('ended', $december['rows'][0]['status']);
        $this->assertFalse($december['needsRate']);
        $this->assertSame(34000.0, FixedExpenses::report($owner, '2026-11')['totalArs']);
    }

    public function test_un_gasto_de_un_recurso_compartido_usa_tu_participacion_y_sin_ella_queda_pendiente(): void
    {
        $owner = User::factory()->create();
        $server = $owner->infrastructureResources()->create(['name' => 'Servidor de sitios', 'provider' => 'digitalocean', 'kind' => 'server']);
        $this->actingAs($owner)->post(route('fixed-expenses.store'), ['name' => 'Droplet sitios', 'category' => 'infrastructure', 'currency' => 'USD', 'infrastructure_resource_id' => $server->id, 'amount' => '6', 'effective_from' => '2026-11'])->assertSessionHasNoErrors();
        FixedExpenses::storeRate($owner, '2026-11', 1668.81, 'arq');

        $report = FixedExpenses::report($owner, '2026-11');
        $this->assertSame('pending_share', $report['rows'][0]['status']);
        $this->assertSame(0.0, $report['totalArs']);
        $this->assertSame(1, $report['pendingShare']);
        $this->get(route('fixed-expenses.index'))->assertOk()->assertSee('Falta participación')->assertSee('Sin contar 1 gasto(s) pendiente(s)')->assertDontSee('3 USD');

        $server->shares()->create(['effective_from' => '2026-11', 'share_percent' => 50]);
        $report = FixedExpenses::report($owner, '2026-11');
        $this->assertSame(3.0, $report['rows'][0]['mine']);
        $this->assertSame(5006.43, $report['totalArs']);
        $this->get(route('fixed-expenses.index'))->assertOk()->assertSee('3 USD (50 %)')->assertSee('$5.006,43');

        // Borrar el recurso haría que el gasto se cuente completo como tuyo.
        $this->delete(route('infrastructure.destroy', $server))->assertSessionHasErrors('resource');
        $this->assertModelExists($server);
    }

    public function test_toma_la_cotizacion_de_arq_al_abrir_el_mes_y_la_conserva_hasta_que_la_actualices(): void
    {
        Http::fake(['api.comparadolar.ar/*' => Http::sequence()->push($this->quotes())->push($this->quotes(['ask' => 1700, 'totalAsk' => 1700]))]);
        $owner = User::factory()->create();
        $this->expense($owner, 'ChatGPT', ['2026-10' => 20]);
        FixedExpenses::storeRate($owner, '2026-10', 1500, 'manual');

        $this->actingAs($owner)->get(route('fixed-expenses.index'))->assertOk()->assertSee('$1.668,81')->assertSee('Compra de USDC en Arq · 03/11 16:37');
        $this->get(route('fixed-expenses.index'))->assertOk();
        Http::assertSentCount(1);

        $rate = ExchangeRate::where('month', '2026-11')->sole();
        $this->assertSame(['1668.8100', 'arq', '2026-11-03 16:37'], [$rate->rate, $rate->source, $rate->quoted_at->format('Y-m-d H:i')]);

        $this->post(route('fixed-expenses.rate.refresh'))->assertSessionHasNoErrors();
        $this->assertSame('1700.0000', $rate->refresh()->rate);
        $this->assertSame('1500.0000', ExchangeRate::where('month', '2026-10')->sole()->rate);
    }

    public function test_si_arq_no_informa_un_precio_vigente_no_guarda_cero_ni_un_precio_viejo(): void
    {
        Http::fake(['api.comparadolar.ar/*' => Http::sequence()
            ->push('error', 500)
            ->push($this->quotes(['stale' => true]))
            ->push([$this->quotes()[0]])
            ->push($this->quotes(['totalAsk' => 0, 'ask' => 0]))
            ->push('no es json')
            ->pushFailedConnection('Tiempo de espera agotado')]);
        $owner = User::factory()->create();
        $this->expense($owner, 'Anthropic', ['2026-11' => 20]);

        $this->actingAs($owner)->get(route('fixed-expenses.index'))->assertOk()->assertSee('No se pudo obtener la cotización de Arq')->assertSee('Sin cotización')->assertDontSee('33.376');
        // Un reintento automático cada 15 minutos como máximo: la segunda visita no vuelve a consultar.
        $this->get(route('fixed-expenses.index'))->assertOk()->assertDontSee('No se pudo obtener la cotización de Arq');
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('fixed-expenses.rate.refresh'))->assertSessionHasErrors('rate');
        }
        $this->assertDatabaseCount('exchange_rates', 0);
        $this->assertSame('pending_rate', FixedExpenses::report($owner, '2026-11')['rows'][0]['status']);
    }

    public function test_la_cotizacion_manual_no_acepta_meses_futuros_ni_valores_invalidos(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('fixed-expenses.rate.store'), ['month' => '2026-12', 'rate' => '1700'])->assertSessionHasErrors('month');
        $this->post(route('fixed-expenses.rate.store'), ['month' => '2026-10', 'rate' => '0'])->assertSessionHasErrors('rate');
        $this->post(route('fixed-expenses.rate.store'), ['month' => '2026-10', 'rate' => 'mil'])->assertSessionHasErrors('rate');
        $this->post(route('fixed-expenses.rate.store'), ['month' => '2026-13', 'rate' => '1500'])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('exchange_rates', 0);

        $this->post(route('fixed-expenses.rate.store'), ['month' => '2026-10', 'rate' => '1500.5'])->assertSessionHasNoErrors();
        $this->assertSame(['1500.5000', 'manual'], [$owner->exchangeRates()->sole()->rate, $owner->exchangeRates()->sole()->source]);
        // Un mes futuro en la URL muestra el mes en curso.
        $this->get(route('fixed-expenses.index', ['mes' => '2027-01']))->assertOk()->assertSee('Noviembre 2026');
    }

    public function test_valida_el_alta_y_no_permite_cambiar_la_moneda(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create()->infrastructureResources()->create(['name' => 'Ajeno', 'provider' => 'other', 'kind' => 'server']);
        $base = ['name' => 'Cursor', 'category' => 'tools', 'currency' => 'USD', 'amount' => '20', 'effective_from' => '2026-11'];
        $this->actingAs($owner)->post(route('fixed-expenses.store'), [...$base, 'currency' => 'EUR'])->assertSessionHasErrors('currency');
        $this->post(route('fixed-expenses.store'), [...$base, 'amount' => '0'])->assertSessionHasErrors('amount');
        $this->post(route('fixed-expenses.store'), [...$base, 'category' => 'inventada'])->assertSessionHasErrors('category');
        $this->post(route('fixed-expenses.store'), [...$base, 'infrastructure_resource_id' => $foreign->id])->assertSessionHasErrors('infrastructure_resource_id');
        $this->assertDatabaseCount('fixed_expenses', 0);

        $this->post(route('fixed-expenses.store'), $base)->assertSessionHasNoErrors();
        $expense = FixedExpense::sole();
        $this->put(route('fixed-expenses.update', $expense), ['name' => 'Cursor Pro', 'category' => 'tools', 'currency' => 'ARS'])->assertSessionHasNoErrors();
        $this->assertSame(['Cursor Pro', 'USD'], [$expense->refresh()->name, $expense->currency]);
    }

    public function test_aisla_los_gastos_entre_usuarios(): void
    {
        $owner = User::factory()->create();
        $expense = $this->expense($owner, 'Suscripción privada', ['2026-11' => 20]);
        $amount = $expense->amounts()->sole();
        FixedExpenses::storeRate($owner, '2026-11', 1668.81, 'arq');
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('fixed-expenses.index'))->assertOk()->assertDontSee('Suscripción privada');
        $this->put(route('fixed-expenses.update', $expense), ['name' => 'Tomado', 'category' => 'tools'])->assertNotFound();
        $this->delete(route('fixed-expenses.destroy', $expense))->assertNotFound();
        $this->post(route('fixed-expenses.amounts.store', $expense), ['effective_from' => '2026-12', 'amount' => '1'])->assertNotFound();
        $this->delete(route('fixed-expenses.amounts.destroy', [$expense, $amount]))->assertNotFound();
        $otherExpense = $this->expense($other, 'Propio', ['2026-11' => 5]);
        $this->delete(route('fixed-expenses.amounts.destroy', [$otherExpense, $amount]))->assertNotFound();

        $this->assertModelExists($amount);
        $this->assertSame('Suscripción privada', $expense->refresh()->name);
        $this->assertNull(FixedExpenses::report($other, '2026-11')['rate']);
    }

    public function test_el_resumen_suma_otros_ingresos_y_gastos_fijos_al_resultado_del_mes(): void
    {
        $owner = User::factory()->create();
        $client = $owner->clients()->create(['name' => 'Ferretería', 'systems' => 1, 'monthly_fee' => 27500, 'due_day' => 10]);
        $this->actingAs($owner)->post(route('clients.payment', $client), ['amount' => 27500, 'billing_month' => '2026-11', 'date' => '2026-11-04'])->assertSessionHasNoErrors();
        $owner->transactions()->create(['type' => 'income', 'area' => 'business', 'description' => 'Cuota 01', 'amount' => 400000, 'date' => '2026-11-04']);
        $owner->transactions()->create(['type' => 'expense', 'area' => 'business', 'description' => 'Dominio', 'amount' => 1000, 'date' => '2026-11-10']);
        $owner->transactions()->create(['type' => 'expense', 'area' => 'personal', 'description' => 'Súper', 'amount' => 5000, 'date' => '2026-11-10']);
        $owner->transactions()->create(['type' => 'income', 'area' => 'business', 'description' => 'Trabajo de octubre', 'amount' => 90000, 'date' => '2026-10-30']);
        $this->expense($owner, 'Anthropic', ['2026-11' => 20]);
        $server = $owner->infrastructureResources()->create(['name' => 'Servidor', 'provider' => 'digitalocean', 'kind' => 'server']);
        $owner->fixedExpenses()->create(['name' => 'Servidor compartido', 'category' => 'infrastructure', 'currency' => 'USD', 'infrastructure_resource_id' => $server->id])->amounts()->create(['effective_from' => '2026-11', 'amount' => 12]);
        FixedExpenses::storeRate($owner, '2026-11', 1500, 'manual');

        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => [$summary['subscriptionIncome'], $summary['otherIncome'], $summary['income'], $summary['recordedExpenses'], $summary['fixedExpenses'], $summary['fixedPending'], $summary['result']]
                === [27500.0, 400000.0, 427500.0, 1000.0, 30000.0, 1, 396500.0])
            ->assertSee('$396.500')->assertSee('−$31.000')->assertSee('Sin contar 1 gasto(s) fijo(s) pendiente(s)');
    }

    public function test_el_resumen_toma_la_cotizacion_del_mes_y_si_falla_no_suma_los_dolares(): void
    {
        Http::fake(['api.comparadolar.ar/*' => Http::sequence()->push('error', 500)->push($this->quotes())]);
        $owner = User::factory()->create();
        $this->expense($owner, 'Anthropic', ['2026-11' => 20]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('No se pudo obtener la cotización de Arq')
            ->assertViewHas('summary', fn ($summary) => $summary['fixedExpenses'] === 0.0 && $summary['fixedPending'] === 1 && $summary['rateFailed']);
        $this->post(route('fixed-expenses.rate.refresh'))->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('No se pudo obtener la cotización de Arq')
            ->assertViewHas('summary', fn ($summary) => $summary['fixedExpenses'] === 33376.2 && $summary['result'] === -33376.2)->assertSee('−$33.376,20');
    }

    public function test_formatea_importes_en_dolares(): void
    {
        $this->assertSame(['20 USD', '10,75 USD', '1.234,50 USD', '0 USD'], [usd(20), usd('10.75'), usd(1234.5), usd(null)]);
    }

    private function expense(User $user, string $name, array $amounts, string $currency = 'USD'): FixedExpense
    {
        $expense = $user->fixedExpenses()->create(['name' => $name, 'category' => 'tools', 'currency' => $currency]);
        foreach ($amounts as $month => $amount) $expense->amounts()->create(['effective_from' => $month, 'amount' => $amount]);

        return $expense;
    }

    // Respuesta de https://api.comparadolar.ar/usdc con el formato real del 2026-11-03.
    private function quotes(array $arq = []): array
    {
        return [
            ['slug' => 'otro', 'ask' => 1500, 'totalAsk' => 1500, 'bid' => 1490, 'totalBid' => 1490, 'time' => '2026-11-03T19:30:00.000Z', 'stale' => false],
            ['id' => 'arq', 'slug' => 'arq', 'ask' => 1668.81, 'totalAsk' => 1668.81, 'bid' => 1664.696158, 'totalBid' => 1664.696158, 'time' => '2026-11-03T19:37:37.310Z', 'stale' => false, ...$arq],
        ];
    }
}
