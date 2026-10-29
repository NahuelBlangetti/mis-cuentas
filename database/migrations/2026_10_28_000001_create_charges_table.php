<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->char('billing_month', 7);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['client_id', 'billing_month']);
        });
    }

    public function down(): void { Schema::dropIfExists('charges'); }
};
