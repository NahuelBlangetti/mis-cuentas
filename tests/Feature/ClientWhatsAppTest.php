<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClientWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-11-02 12:00'));
    }

    public function test_normaliza_celulares_argentinos_e_internacionales(): void
    {
        $cases = [
            '351 1234567' => '5493511234567', '0351-1234567' => '5493511234567', '11 2345-6789' => '5491123456789',
            '+54 9 351 123-4567' => '5493511234567', '+54 351 1234567' => '5493511234567', '5493511234567' => '5493511234567',
            '00 54 9 351 1234567' => '5493511234567', '+598 99 123 456' => '59899123456', '+1 (305) 555-0100' => '13055550100',
        ];
        foreach ($cases as $input => $expected) $this->assertSame($expected, WhatsApp::normalize($input), $input);

        foreach (['0351 15 1234567', '1234', 'abc', '+54 12'] as $invalid) $this->assertNull(WhatsApp::normalize($invalid), $invalid);
        $this->assertSame('+54 9 351 123-4567', WhatsApp::display('5493511234567'));
    }

    public function test_se_guarda_y_se_edita_el_celular_del_cliente(): void
    {
        $this->actingAs($this->user)->post(route('clients.store'), $this->data(['phone' => '0351 123-4567']))->assertRedirect()->assertSessionHasNoErrors();
        $client = Client::sole();
        $this->assertSame('5493511234567', $client->phone);

        $this->actingAs($this->user)->put(route('clients.update', $client), $this->data(['name' => 'Juan Editado', 'phone' => '+54 9 11 2345-6789', 'due_day' => 15]))->assertSessionHasNoErrors();
        $client->refresh();
        $this->assertSame(['Juan Editado', '5491123456789', 15], [$client->name, $client->phone, $client->due_day]);

        $this->actingAs($this->user)->put(route('clients.update', $client), $this->data(['phone' => '']))->assertSessionHasNoErrors();
        $this->assertNull($client->fresh()->phone);
    }

    public function test_un_celular_invalido_no_se_guarda(): void
    {
        $this->actingAs($this->user)->post(route('clients.store'), $this->data(['phone' => '0351 15 1234567']))->assertSessionHasErrors('phone');
        $this->assertSame(0, Client::count());
    }

    public function test_el_mensaje_de_cobro_lista_los_meses_adeudados(): void
    {
        $client = $this->user->clients()->create($this->data(['name' => 'Juan Pérez', 'phone' => '5493511234567']));

        $closing = "\n\nPodés transferir al alias BLANGETTI.NAHUEL.\n\n¿Ves todo correcto o necesitás algo? Estoy a disposición. ¡Gracias!";
        $this->assertSame("¡Hola Juan Pérez! Te paso el abono de noviembre 2026: $27.500.\nVence el día 10.".$closing, WhatsApp::chargeMessage($client, ['2026-11' => 27500]));
        $this->assertSame(
            "¡Hola Juan Pérez! Te paso el detalle de los abonos pendientes:\n• Octubre 2026: $27.500\n• Noviembre 2026: $27.500,50\nTotal: $55.000,50.".$closing,
            WhatsApp::chargeMessage($client, ['2026-11' => 27500.5, '2026-10' => 27500]),
        );
    }

    public function test_el_alias_del_mensaje_se_puede_cambiar_o_quitar(): void
    {
        $client = $this->user->clients()->create($this->data());

        config(['services.transfer.alias' => 'OTRO.ALIAS']);
        $this->assertStringContainsString('alias OTRO.ALIAS.', WhatsApp::chargeMessage($client, ['2026-11' => 27500]));
        config(['services.transfer.alias' => '']);
        $this->assertStringNotContainsString('alias', WhatsApp::chargeMessage($client, ['2026-11' => 27500]));
    }

    public function test_el_boton_de_whatsapp_aparece_solo_si_hay_celular_y_deuda(): void
    {
        $withPhone = $this->user->clients()->create($this->data(['name' => 'Con celular', 'phone' => '5493511234567']));
        $this->user->clients()->create($this->data(['name' => 'Sin celular']));

        $page = $this->actingAs($this->user)->get(route('clients.index'))->assertOk();
        $page->assertSee('https://wa.me/5493511234567?text=', false)->assertSee('Cargá su celular para enviarle el cobro por WhatsApp');
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('https://wa.me/5493511234567?text=', false);

        $this->actingAs($this->user)->post(route('clients.payment', $withPhone), ['amount' => 27500, 'date' => '2026-11-02', 'billing_month' => '2026-11'])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->get(route('clients.index'))->assertDontSee('?text=', false);
    }

    public function test_el_chat_de_whatsapp_se_abre_desde_la_cartera_aunque_no_deba(): void
    {
        $this->user->clients()->create($this->data(['name' => 'Con celular', 'phone' => '5493511234567', 'active' => false]));
        $this->user->clients()->create($this->data(['name' => 'Sin celular', 'active' => false]));

        $page = $this->actingAs($this->user)->get(route('clients.index'))->assertOk();
        $page->assertSee('href="https://wa.me/5493511234567"', false)->assertSee('Abrir chat')->assertDontSee('?text=', false);
        $this->assertSame(1, substr_count($page->getContent(), 'href="https://wa.me/'));
    }

    private function data(array $attributes = []): array
    {
        return ['name' => 'Juan', 'systems' => 1, 'monthly_fee' => 27500, 'due_day' => 10, ...$attributes];
    }
}
