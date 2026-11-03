<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_el_propietario_configurado_puede_iniciar_sesion(): void
    {
        config(['owner.email' => 'nahuel@example.com']);
        $other = User::factory()->create(['password' => 'una-clave-larga']);
        $this->post(route('login.store'), ['email' => $other->email, 'password' => 'una-clave-larga'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $owner = User::factory()->create(['email' => 'nahuel@example.com', 'password' => 'una-clave-larga']);
        $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'una-clave-larga'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_una_sesion_preexistente_de_otro_usuario_se_revoca(): void
    {
        config(['owner.email' => 'nahuel@example.com']);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertForbidden();
        $this->assertGuest();
    }

    public function test_produccion_sin_propietario_bloquea_login_y_sesiones(): void
    {
        config(['owner.email' => '']);
        $this->app->instance('env', 'production');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = User::factory()->create(['password' => 'una-clave-larga']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'una-clave-larga'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get(route('clients.index'))->assertForbidden();
    }
}
