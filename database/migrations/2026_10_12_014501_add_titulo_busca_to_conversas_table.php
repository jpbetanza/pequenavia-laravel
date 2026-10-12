<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Cria a coluna do título normalizado (minúsculas, sem acento) e preenche as conversas que já existem.
     */
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->string('titulo_busca', 255)->default('');
        });

        DB::table('conversas')->select(['id', 'titulo'])->chunkById(500, function ($conversas) {
            foreach ($conversas as $conversa) {
                DB::table('conversas')
                    ->where('id', $conversa->id)
                    ->update(['titulo_busca' => Str::lower(Str::ascii($conversa->titulo))]);
            }
        });
    }

    /**
     * Remove a coluna do título normalizado.
     */
    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropColumn('titulo_busca');
        });
    }
};
