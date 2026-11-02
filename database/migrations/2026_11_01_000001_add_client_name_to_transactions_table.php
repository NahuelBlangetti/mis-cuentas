<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Nombre de un cliente ocasional (pago único) que no está registrado en Clientes.
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('client_name', 160)->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });
    }
};
