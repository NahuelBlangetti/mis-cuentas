<?php

namespace Tests\Feature;

use App\Models\InstallationDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Producción ya tiene client_installations con credenciales cifradas: la migración debe moverlas sin perderlas ni descifrarlas.
class SplitInstallationDatabasesMigrationTest extends TestCase
{
    public function test_mueve_las_credenciales_existentes_y_se_puede_revertir(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $migration = require database_path('migrations/2026_11_03_000003_split_installation_databases.php');
        $migration->down();

        $connection = ['host' => 'database.example.com', 'port' => '3306', 'database' => 'production', 'username' => 'lector', 'password' => 'secreto'];
        $encrypted = Crypt::encryptString(json_encode($connection));
        $client = User::factory()->create()->clients()->create(['name' => 'Cliente', 'systems' => 1, 'monthly_fee' => 100, 'due_day' => 10]);
        $id = DB::table('client_installations')->insertGetId(['client_id' => $client->id, 'name' => 'Sistema', 'url' => 'https://example.com', 'connection' => $encrypted, 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();
        $database = InstallationDatabase::sole();
        $this->assertSame($id, $database->client_installation_id);
        $this->assertSame('Principal', $database->name);
        $this->assertSame($connection, $database->connection);
        $this->assertSame($encrypted, DB::table('installation_databases')->value('connection'));

        $migration->down();
        $this->assertSame($encrypted, DB::table('client_installations')->where('id', $id)->value('connection'));
        $migration->up();
    }

    // Esta prueba no usa transacciones: la próxima clase con RefreshDatabase vuelve a crear el esquema desde cero.
    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }
}
