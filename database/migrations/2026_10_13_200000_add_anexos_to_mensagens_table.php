<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anexos da mensagem do usuário: metadados de cada arquivo e, nos de texto (planilha convertida, .txt), o conteúdo.
     */
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            $table->jsonb('anexos')->nullable();
        });
    }

    /**
     * Remove a coluna de anexos.
     */
    public function down(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            $table->dropColumn('anexos');
        });
    }
};
