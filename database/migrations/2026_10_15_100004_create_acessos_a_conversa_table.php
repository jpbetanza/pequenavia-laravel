<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de quando um gestor abriu o detalhe de uma conversa.
     */
    public function up(): void
    {
        Schema::create('acessos_a_conversa', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreignUuid('conversa_id')->constrained('conversas')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Remove os registros de acesso.
     */
    public function down(): void
    {
        Schema::dropIfExists('acessos_a_conversa');
    }
};
