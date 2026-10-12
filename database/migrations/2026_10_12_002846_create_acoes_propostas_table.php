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
        Schema::create('acoes_propostas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversa_id')->constrained('conversas')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->jsonb('dados');
            $table->string('status', 12)->index();
            $table->uuid('resultado_id')->nullable();
            $table->timestamp('confirmada_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acoes_propostas');
    }
};
