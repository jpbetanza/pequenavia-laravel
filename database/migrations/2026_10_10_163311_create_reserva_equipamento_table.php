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
        Schema::create('reserva_equipamento', function (Blueprint $table) {
            $table->foreignUuid('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->deferrable()->initiallyImmediate(false);
            $table->unsignedInteger('quantidade');
            $table->timestamp('entregue_em')->nullable();
            $table->timestamp('recolhido_em')->nullable();

            $table->primary(['reserva_id', 'equipamento_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserva_equipamento');
    }
};
