<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Servidores y clústeres se registran una sola vez, aunque los usen varios clientes.
    public function up(): void
    {
        Schema::create('infrastructure_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('provider', 30);
            $table->string('kind', 30);
            // Identificador del proveedor (ej. UUID de DigitalOcean) para no importar dos veces el mismo recurso.
            $table->string('external_id')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider', 'external_id'], 'infrastructure_resources_external_unique');
        });

        // Tu porcentaje del costo, con mes de vigencia. Sin registros, la participación queda pendiente.
        Schema::create('infrastructure_resource_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infrastructure_resource_id')->constrained(indexName: 'resource_shares_resource_foreign')->cascadeOnDelete();
            $table->char('effective_from', 7);
            $table->decimal('share_percent', 5, 2);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['infrastructure_resource_id', 'effective_from'], 'resource_shares_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_resource_shares');
        Schema::dropIfExists('infrastructure_resources');
    }
};
