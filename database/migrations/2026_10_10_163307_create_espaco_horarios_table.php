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
        Schema::create('espaco_horarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('espaco_id')->constrained('espacos')->cascadeOnDelete();
            $table->tinyInteger('dia_semana');
            $table->time('abre');
            $table->time('fecha');
            $table->timestamps();

            $table->unique(['espaco_id', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('espaco_horarios');
    }
};
