<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('espaco_bloqueios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('espaco_id')->constrained('espacos')->cascadeOnDelete();
            $table->date('inicio');
            $table->date('fim');
            $table->string('motivo', 160);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('espaco_bloqueios');
    }
};
