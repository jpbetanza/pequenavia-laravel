<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aparelhos que aceitaram receber notificações: o endereço do serviço de push e as chaves de criptografia de cada um.
     */
    public function up(): void
    {
        Schema::create('inscricoes_push', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint')->unique();
            $table->string('chave_p256dh');
            $table->string('chave_auth');
            $table->string('dispositivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove as inscrições.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscricoes_push');
    }
};
