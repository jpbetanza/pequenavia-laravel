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
        Schema::create('solicitacao_equipamento', function (Blueprint $table) {
            $table->foreignUuid('solicitacao_id')->constrained('solicitacoes')->cascadeOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->cascadeOnDelete();
            $table->unsignedInteger('quantidade')->default(1);

            $table->primary(['solicitacao_id', 'equipamento_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacao_equipamento');
    }
};
