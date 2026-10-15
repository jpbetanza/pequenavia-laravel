<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custo em dólares que o OpenRouter informa por resposta, e quantos tokens de entrada vieram do cache de prompt.
     */
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            $table->unsignedInteger('tokens_do_cache')->nullable()->after('tokens_saida');
            $table->decimal('custo', 12, 6)->nullable()->after('tokens_do_cache');
        });
    }

    public function down(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            $table->dropColumn(['tokens_do_cache', 'custo']);
        });
    }
};
