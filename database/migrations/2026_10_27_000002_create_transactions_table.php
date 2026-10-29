<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 12);
            $table->string('area', 12);
            $table->string('description');
            $table->string('category')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->char('billing_month', 7)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'billing_month', 'client_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('transactions'); }
};
