<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_la_cuenta_con_los_cinco_clientes_de_ejemplo(): void
    {
        $this->artisan('app:create-owner')
            ->expectsQuestion('Tu nombre', 'Nahuel')
            ->expectsQuestion('Tu correo para iniciar sesión', 'dueno@example.com')
            ->expectsQuestion('Elegí una contraseña de al menos 12 caracteres', 'una-clave-larga')
            ->expectsQuestion('Repetí la contraseña', 'una-clave-larga')
            ->assertSuccessful();

        $owner = User::query()->sole();
        $this->assertTrue(Hash::check('una-clave-larga', $owner->password));
        $this->assertSame(5, $owner->clients()->count());
        $this->assertEquals(152500, $owner->clients()->sum('monthly_fee'));
    }

    public function test_no_crea_una_segunda_cuenta(): void
    {
        User::factory()->create();

        $this->artisan('app:create-owner')->assertFailed();
        $this->assertSame(1, User::query()->count());
    }
}
