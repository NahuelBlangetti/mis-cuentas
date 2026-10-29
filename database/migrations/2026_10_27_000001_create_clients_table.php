<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('systems')->default(1);
            $table->decimal('monthly_fee', 12, 2);
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void { Schema::dropIfExists('clients'); }
};
