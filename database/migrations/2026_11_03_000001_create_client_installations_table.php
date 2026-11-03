<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('url', 2048);
            $table->text('connection');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_installations');
    }
};
