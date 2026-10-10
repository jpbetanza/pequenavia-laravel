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
        Schema::create('espacos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 120);
            $table->unsignedInteger('capacidade')->nullable();
            $table->string('localizacao', 160)->nullable();
            $table->string('cor', 10);
            $table->jsonb('recursos_fixos')->default('[]');
            $table->boolean('aceita_solicitacoes')->default(true);
            $table->unsignedInteger('antecedencia_min_horas')->default(0);
            $table->unsignedInteger('duracao_max_min')->nullable();
            $table->unsignedInteger('intervalo_min')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'nome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('espacos');
    }
};
