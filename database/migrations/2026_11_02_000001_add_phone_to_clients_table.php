<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Celular de WhatsApp en formato internacional, solo dígitos (ej. 5493511234567).
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
