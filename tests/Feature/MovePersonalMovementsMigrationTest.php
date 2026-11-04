<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Producción puede tener gastos personales cargados en Movimientos: la migración los pasa a Finanzas personales sin perderlos.
class MovePersonalMovementsMigrationTest extends TestCase
{
    public function test_mueve_los_gastos_personales_deja_los_ingresos_y_se_puede_revertir(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $migration = require database_path('migrations/2026_11_04_000003_move_personal_movements_to_personal_expenses.php');
        $user = User::factory()->create();
        $row = fn (array $data) => DB::table('transactions')->insertGetId(['user_id' => $user->id, 'type' => 'expense', 'area' => 'personal', 'amount' => 1000, 'date' => '2026-10-05', 'created_at' => now(), 'updated_at' => now(), ...$data]);
        $row(['description' => 'Súper', 'category' => 'Comida']);
        $row(['description' => 'Luz', 'category' => ' servicios ']);
        $row(['description' => 'Regalo', 'category' => 'cumpleaños']);
        $row(['description' => 'Kiosco', 'category' => null]);
        $income = $row(['type' => 'income', 'description' => 'Sueldo', 'category' => 'Sueldo']);
        $business = $row(['area' => 'business', 'description' => 'Hosting', 'category' => 'Servicios']);

        $migration->up();
        $this->assertSame([
            ['Súper', 'food'], ['Luz', 'utilities'], ['Regalo · cumpleaños', 'other'], ['Kiosco', 'other'],
        ], DB::table('personal_expenses')->orderBy('id')->get()->map(fn ($expense) => [$expense->description, $expense->category])->all());
        $this->assertSame(4000.0, (float) DB::table('personal_expenses')->where('user_id', $user->id)->sum('amount'));
        $this->assertSame([$income, $business], DB::table('transactions')->orderBy('id')->pluck('id')->all());

        $migration->down();
        $this->assertSame(0, DB::table('personal_expenses')->count());
        $this->assertSame(4, DB::table('transactions')->where('area', 'personal')->where('type', 'expense')->count());
        $this->assertSame('Súper y comida', DB::table('transactions')->where('description', 'Súper')->value('category'));
        $migration->up();
    }

    // Esta prueba no usa transacciones: la próxima clase con RefreshDatabase vuelve a crear el esquema desde cero.
    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }
}
