<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gastos personales del mes (súper, salidas, compras), aparte de los movimientos del negocio. Siempre en pesos.
        Schema::create('personal_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description', 180);
            $table->string('category', 30);
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        // Gastos personales que se pagan todos los meses (alquiler, servicios, suscripciones), en pesos o en dólares.
        Schema::create('personal_recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('category', 30);
            // Fija desde el alta: cambiarla reinterpretaría todos los importes cargados.
            $table->char('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Importe con mes de vigencia, como en los gastos fijos del negocio. Un importe 0 significa que desde ese mes se dejó de pagar.
        Schema::create('personal_recurring_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained('personal_recurring_expenses')->cascadeOnDelete();
            $table->char('effective_from', 7);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            // Nombre corto: el generado supera los 64 caracteres que admite MySQL.
            $table->unique(['recurring_expense_id', 'effective_from'], 'personal_recurring_amounts_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_recurring_amounts');
        Schema::dropIfExists('personal_recurring_expenses');
        Schema::dropIfExists('personal_expenses');
    }
};
