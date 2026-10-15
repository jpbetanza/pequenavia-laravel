<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leitura de uma conversa feita pelo JEV e pelo Haiku. Uma por conversa; reclassificada quando chegam mensagens novas.
     */
    public function up(): void
    {
        Schema::create('classificacoes_de_conversa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversa_id')->unique()->constrained('conversas')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->foreignUuid('rodada_id')->nullable()->constrained('rodadas_de_classificacao')->nullOnDelete();
            $table->timestamp('mensagens_ate');
            $table->string('caso_de_uso', 30);
            $table->float('caso_confianca');
            $table->string('resultado', 20);
            $table->float('resultado_confianca');
            $table->boolean('abandonada');
            $table->float('frustracao');
            $table->float('frustracao_confianca');
            $table->float('confusa');
            $table->float('pedido_fora');
            $table->float('erro_do_assistente');
            $table->foreignUuid('tema_id')->nullable()->constrained('temas_de_pedido')->nullOnDelete();
            $table->string('tema_origem', 10)->nullable();
            $table->float('tema_confianca')->nullable();
            $table->boolean('a_revisar')->default(false);
            $table->text('resumo')->nullable();
            $table->jsonb('ferramentas')->default('[]');
            $table->integer('acoes_propostas');
            $table->integer('acoes_confirmadas');
            $table->integer('mensagens');
            $table->integer('tokens_assistente_entrada');
            $table->integer('tokens_assistente_saida');
            $table->string('modelo_jev', 40)->nullable();
            $table->jsonb('correcao')->nullable();
            $table->timestamp('classificada_em');
            $table->timestamps();

            $table->index(['caso_de_uso', 'resultado']);
            $table->index('classificada_em');
        });
    }

    /**
     * Remove as classificações.
     */
    public function down(): void
    {
        Schema::dropIfExists('classificacoes_de_conversa');
    }
};
