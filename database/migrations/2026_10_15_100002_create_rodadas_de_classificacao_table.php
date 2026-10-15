<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada execução do classificador de conversas, com os totais e os custos.
     */
    public function up(): void
    {
        Schema::create('rodadas_de_classificacao', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('iniciada_em');
            $table->timestamp('terminada_em')->nullable();
            $table->integer('conversas_lidas')->default(0);
            $table->integer('resumidas')->default(0);
            $table->integer('temas_novos')->default(0);
            $table->integer('falhas')->default(0);
            $table->integer('tokens_jev')->default(0);
            $table->decimal('custo_jev', 10, 6)->default(0);
            $table->decimal('custo_haiku', 10, 6)->default(0);
            $table->text('erro')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove as rodadas.
     */
    public function down(): void
    {
        Schema::dropIfExists('rodadas_de_classificacao');
    }
};
