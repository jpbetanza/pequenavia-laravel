<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedido do usuário (mensagem) que gerou a proposta. Propostas do mesmo pedido não se expiram umas às outras.
     */
    public function up(): void
    {
        Schema::table('acoes_propostas', function (Blueprint $table) {
            $table->uuid('pedido_id')->nullable();
        });
    }

    /**
     * Remove o pedido da proposta.
     */
    public function down(): void
    {
        Schema::table('acoes_propostas', function (Blueprint $table) {
            $table->dropColumn('pedido_id');
        });
    }
};
