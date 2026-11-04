<?php

namespace Tests\Feature;

use App\Models\PersonalExpense;
use App\Models\PersonalRecurringExpense;
use App\Models\User;
use App\Services\FixedExpenses;
use App\Services\PersonalExpenses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonalFinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 11, 15, 12));
        Http::preventStrayRequests();
        $this->user = User::factory()->create();
    }

    public function test_suma_recurrentes_y_gastos_del_mes_por_categoria(): void
    {
        $this->actingAs($this->user)->post(route('personal.recurring.store'), ['form' => 'recurring', 'name' => 'Alquiler', 'category' => 'housing', 'currency' => 'ARS', 'amount' => '400000', 'effective_from' => '2026-10'])
            ->assertSessionHasNoErrors()->assertRedirect(route('personal.index', ['mes' => '2026-10']));
        $this->recurring('Luz', 'utilities', ['2026-11' => 30000]);
        $this->post(route('personal.expenses.store'), ['form' => 'expense', 'description' => 'Súper', 'category' => 'food', 'amount' => '85000.50', 'date' => '2026-11-03'])
            ->assertSessionHasNoErrors()->assertRedirect(route('personal.index', ['mes' => '2026-11']));
        $this->expense('Verdulería', 'food', 14999.5, '2026-11-10');
        $this->expense('Cine de octubre', 'leisure', 20000, '2026-10-20');

        $report = PersonalExpenses::report($this->user, '2026-11');
        $this->assertSame([430000.0, 100000.0, 530000.0, 2], [$report['recurringTotal'], $report['monthTotal'], $report['total'], $report['activeRecurring']]);
        $this->assertSame(['Vivienda' => 400000.0, 'Súper y comida' => 100000.0, 'Servicios' => 30000.0], $report['categories']->pluck('amount', 'label')->all());
        $this->assertSame(75, $report['categories']->first()['percent']);

        // La luz empieza en noviembre: octubre solo tiene el alquiler y lo cargado en octubre.
        $this->assertSame(420000.0, PersonalExpenses::report($this->user, '2026-10')['total']);
        $this->get(route('personal.index'))->assertOk()->assertSee('$530.000')->assertSee('Verdulería')->assertDontSee('Cine de octubre')
            ->assertSee('mes=2026-10')->assertDontSee('mes=2026-12');
        $this->get(route('personal.index', ['mes' => '2026-10']))->assertOk()->assertSee('Cine de octubre')->assertSee('Desde noviembre 2026')->assertSee('mes=2026-11');
        $this->get(route('personal.index', ['mes' => '2026-13']))->assertOk()->assertSee('Verdulería');
    }

    public function test_un_cambio_de_precio_rige_desde_su_mes_y_un_importe_cero_lo_da_de_baja(): void
    {
        $internet = $this->recurring('Internet', 'utilities', ['2026-09' => 20000]);

        $this->actingAs($this->user)->post(route('personal.recurring.amounts.store', $internet), ['effective_from' => '2026-11', 'amount' => '25000'])->assertSessionHasNoErrors();
        $this->post(route('personal.recurring.amounts.store', $internet), ['effective_from' => '2026-11', 'amount' => '1'])->assertSessionHasErrors('effective_from');
        $this->post(route('personal.recurring.amounts.store', $internet), ['effective_from' => '2026-12', 'amount' => '0'])->assertSessionHasNoErrors();

        $this->assertSame(20000.0, PersonalExpenses::report($this->user, '2026-10')['total']);
        $this->assertSame(25000.0, PersonalExpenses::report($this->user, '2026-11')['total']);
        $december = PersonalExpenses::report($this->user, '2026-12');
        $this->assertSame(['ended', 0.0, 0], [$december['recurring'][0]['status'], $december['total'], $december['activeRecurring']]);
    }

    public function test_un_recurrente_en_dolares_usa_la_cotizacion_del_mes_y_sin_ella_queda_pendiente(): void
    {
        Http::fake(['api.comparadolar.ar/*' => Http::response('error', 500)]);
        $this->recurring('iCloud', 'subscriptions', ['2026-11' => 3], 'USD');
        $this->expense('Súper', 'food', 1000, '2026-11-02');

        $this->actingAs($this->user)->get(route('personal.index'))->assertOk()->assertSee('No se pudo obtener la cotización de Arq')
            ->assertSee('Falta cotización')->assertSee('Sin contar 1 recurrente(s) en dólares sin cotización')->assertViewHas('total', 1000.0);

        FixedExpenses::storeRate($this->user, '2026-11', 1500, 'manual');
        $this->get(route('personal.index'))->assertOk()->assertDontSee('No se pudo obtener la cotización')->assertViewHas('total', 5500.0)->assertSee('$4.500');
    }

    public function test_un_gasto_se_edita_desde_la_lista_y_vuelve_a_su_mes(): void
    {
        $expense = $this->expense('Farmacia', 'health', 9000, '2026-11-04');

        $this->actingAs($this->user)->get(route('personal.index', ['editar' => $expense->id]))->assertOk()
            ->assertSee('Editar gasto')->assertSee('value="Farmacia"', false)->assertSee(route('personal.expenses.update', $expense));
        $this->put(route('personal.expenses.update', $expense), ['form' => 'expense', 'description' => 'Farmacia y médico', 'category' => 'health', 'amount' => '12000', 'date' => '2026-10-28'])
            ->assertSessionHasNoErrors()->assertRedirect(route('personal.index', ['mes' => '2026-10']));
        $this->assertSame(['Farmacia y médico', '12000.00', '2026-10-28'], [$expense->refresh()->description, $expense->amount, $expense->date->toDateString()]);

        $this->post(route('personal.expenses.store'), ['form' => 'expense', 'description' => 'Sin categoría', 'amount' => '1', 'date' => '2026-11-04'])->assertSessionHasErrors('category');
        $this->post(route('personal.expenses.store'), ['form' => 'expense', 'description' => 'Inventada', 'category' => 'yacht', 'amount' => '1', 'date' => '2026-11-04'])->assertSessionHasErrors('category');
        $this->delete(route('personal.expenses.destroy', $expense))->assertRedirect(route('personal.index', ['mes' => '2026-10']));
        $this->assertModelMissing($expense);
    }

    public function test_no_se_pueden_ver_ni_tocar_los_gastos_de_otro_usuario(): void
    {
        $other = User::factory()->create();
        $expense = $other->personalExpenses()->create(['description' => 'Privado', 'category' => 'other', 'amount' => 100, 'date' => '2026-11-01']);
        $recurring = $other->personalRecurringExpenses()->create(['name' => 'Gimnasio privado', 'category' => 'health', 'currency' => 'ARS']);
        $amount = $recurring->amounts()->create(['effective_from' => '2026-11', 'amount' => 100]);
        $mine = $this->recurring('Propio', 'other', ['2026-11' => 5]);

        $this->actingAs($this->user)->get(route('personal.index', ['editar' => $expense->id]))->assertOk()->assertDontSee('Privado')->assertDontSee('Gimnasio privado')->assertSee('Cargar gasto');
        $this->put(route('personal.expenses.update', $expense), ['description' => 'Tomado', 'category' => 'other', 'amount' => 1, 'date' => '2026-11-01'])->assertNotFound();
        $this->delete(route('personal.expenses.destroy', $expense))->assertNotFound();
        $this->put(route('personal.recurring.update', $recurring), ['name' => 'Tomado', 'category' => 'other'])->assertNotFound();
        $this->delete(route('personal.recurring.destroy', $recurring))->assertNotFound();
        $this->post(route('personal.recurring.amounts.store', $recurring), ['effective_from' => '2026-12', 'amount' => '1'])->assertNotFound();
        $this->delete(route('personal.recurring.amounts.destroy', [$recurring, $amount]))->assertNotFound();
        $this->delete(route('personal.recurring.amounts.destroy', [$mine, $amount]))->assertNotFound();

        $this->assertSame(['Privado', 'Gimnasio privado'], [$expense->refresh()->description, $recurring->refresh()->name]);
        $this->assertModelExists($amount);
    }

    public function test_el_objetivo_compara_lo_cobrado_a_clientes_con_los_gastos_personales_y_del_negocio(): void
    {
        $client = $this->user->clients()->create(['name' => 'Ferretería', 'systems' => 1, 'monthly_fee' => 300000, 'due_day' => 10]);
        $this->actingAs($this->user)->post(route('clients.payment', $client), ['amount' => 300000, 'billing_month' => '2026-11', 'date' => '2026-11-05'])->assertSessionHasNoErrors();
        $this->user->transactions()->create(['type' => 'income', 'area' => 'business', 'client_id' => $client->id, 'description' => 'Módulo extra', 'amount' => 100000, 'date' => '2026-11-08']);
        $this->user->transactions()->create(['type' => 'expense', 'area' => 'business', 'description' => 'Dominio', 'amount' => 20000, 'date' => '2026-11-09']);
        $this->user->fixedExpenses()->create(['name' => 'Hosting', 'category' => 'tools', 'currency' => 'ARS'])->amounts()->create(['effective_from' => '2026-11', 'amount' => 30000]);
        $this->recurring('Alquiler', 'housing', ['2026-11' => 400000]);
        $this->expense('Súper', 'food', 50000, '2026-11-03');

        $expected = ['subscriptionIncome' => 300000.0, 'otherIncome' => 100000.0, 'income' => 400000.0, 'personalExpenses' => 450000.0,
            'businessExpenses' => 50000.0, 'expenses' => 500000.0, 'balance' => -100000.0, 'coverage' => 80, 'pending' => 0];
        $this->get(route('personal.index'))->assertOk()->assertViewHas('goal', $expected)
            ->assertSee('Objetivo personal')->assertSee('80 %')->assertSee('Te faltan $100.000 para cubrir todos los gastos.');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('goal', $expected)->assertSee('Ver gastos personales');

        $this->user->transactions()->create(['type' => 'income', 'area' => 'business', 'client_name' => 'Kiosco', 'description' => 'Instalación', 'amount' => 150000, 'date' => '2026-11-12']);
        $this->get(route('personal.index'))->assertOk()->assertViewHas('goal', fn ($goal) => [$goal['coverage'], $goal['balance']] === [110, 50000.0])
            ->assertSee('Cubierto: te sobran $50.000 de lo cobrado.');
    }

    public function test_sin_gastos_el_objetivo_no_muestra_porcentaje(): void
    {
        $this->actingAs($this->user)->get(route('personal.index'))->assertOk()->assertViewHas('goal', fn ($goal) => $goal['coverage'] === null)
            ->assertSee('Cargá tus gastos personales y fijos para medir el objetivo.');
    }

    private function recurring(string $name, string $category, array $amounts, string $currency = 'ARS'): PersonalRecurringExpense
    {
        $expense = $this->user->personalRecurringExpenses()->create(['name' => $name, 'category' => $category, 'currency' => $currency]);
        foreach ($amounts as $month => $amount) $expense->amounts()->create(['effective_from' => $month, 'amount' => $amount]);

        return $expense;
    }

    private function expense(string $description, string $category, float $amount, string $date): PersonalExpense
    {
        return $this->user->personalExpenses()->create(['description' => $description, 'category' => $category, 'amount' => $amount, 'date' => $date]);
    }
}
