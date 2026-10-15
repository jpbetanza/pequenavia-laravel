<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temas de pedido: necessidades que a secretaria tem e o app ainda não cobre.
     */
    public function up(): void
    {
        Schema::create('temas_de_pedido', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 80)->unique();
            $table->string('descricao', 300)->nullable();
            $table->string('situacao', 16)->default('novo');
            $table->string('criado_por', 10);
            $table->uuid('juntado_em_id')->nullable();
            $table->timestamps();
        });

        // A chave estrangeira para a própria tabela vem depois do create, senão o Postgres não acha a chave primária.
        Schema::table('temas_de_pedido', function (Blueprint $table) {
            $table->foreign('juntado_em_id')->references('id')->on('temas_de_pedido')->nullOnDelete();
        });
    }

    /**
     * Remove os temas.
     */
    public function down(): void
    {
        Schema::dropIfExists('temas_de_pedido');
    }
};
