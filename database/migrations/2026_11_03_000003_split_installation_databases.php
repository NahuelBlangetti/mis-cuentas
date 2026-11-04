<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Separa la instalación (sistema y servidor donde corre) de sus bases (credenciales y clúster que las aloja).
    public function up(): void
    {
        Schema::create('installation_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('infrastructure_resource_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->text('connection');
            $table->timestamps();
        });

        Schema::table('client_installations', function (Blueprint $table) {
            $table->foreignId('infrastructure_resource_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        // Copia el texto cifrado tal cual: lo descifra la misma APP_KEY y las credenciales no quedan en claro durante la migración.
        DB::transaction(function () {
            DB::table('client_installations')->orderBy('id')->each(function ($installation) {
                DB::table('installation_databases')->insert([
                    'client_installation_id' => $installation->id, 'name' => 'Principal', 'connection' => $installation->connection,
                    'created_at' => $installation->created_at, 'updated_at' => $installation->updated_at,
                ]);
            });
        });

        Schema::table('client_installations', function (Blueprint $table) {
            $table->dropColumn('connection');
        });
    }

    public function down(): void
    {
        Schema::table('client_installations', function (Blueprint $table) {
            $table->text('connection')->nullable();
        });

        // Vuelve a dejar una sola conexión por instalación: la primera base registrada.
        DB::table('installation_databases')->orderBy('id')->each(function ($database) {
            DB::table('client_installations')->where('id', $database->client_installation_id)->whereNull('connection')->update(['connection' => $database->connection]);
        });

        Schema::table('client_installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('infrastructure_resource_id');
        });
        Schema::dropIfExists('installation_databases');
    }
};
