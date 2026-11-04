<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Alias interno del cliente, por ejemplo el nombre del sistema que usa. No se muestra en los mensajes al cliente.
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('alias', 80)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
