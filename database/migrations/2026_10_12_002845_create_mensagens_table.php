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
        Schema::create('mensagens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversa_id')->constrained('conversas')->cascadeOnDelete();
            $table->string('papel', 12);
            $table->text('conteudo')->nullable();
            $table->jsonb('cartoes')->default('[]');
            $table->jsonb('chamadas')->nullable();
            $table->string('ferramenta_id')->nullable();
            $table->unsignedInteger('tokens_entrada')->nullable();
            $table->unsignedInteger('tokens_saida')->nullable();
            $table->boolean('erro')->default(false);
            $table->timestamps();

            $table->index(['conversa_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensagens');
    }
};
