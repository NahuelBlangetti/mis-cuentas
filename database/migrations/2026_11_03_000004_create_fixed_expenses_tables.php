<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suscripciones y servicios que se pagan todos los meses, en pesos o en dólares.
        // Si el gasto es la factura de un recurso de infraestructura, tu parte sale de la participación de ese recurso.
        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('infrastructure_resource_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('category', 30);
            // Fija desde el alta: cambiarla reinterpretaría todos los importes cargados.
            $table->char('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Importe con mes de vigencia: un cambio de plan crea un registro nuevo y los meses anteriores conservan el suyo.
        // Un importe 0 significa que desde ese mes se dejó de pagar.
        Schema::create('fixed_expense_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_expense_id')->constrained()->cascadeOnDelete();
            $table->char('effective_from', 7);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['fixed_expense_id', 'effective_from']);
        });

        // Una cotización por mes y moneda. Queda guardada: los meses anteriores no se recalculan con el precio del día.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->char('month', 7);
            $table->decimal('rate', 14, 4);
            $table->string('source', 30);
            $table->timestamp('quoted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'currency', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('fixed_expense_amounts');
        Schema::dropIfExists('fixed_expenses');
    }
};
