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
        Schema::create('solicitacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('grupo_id')->nullable()->constrained('grupos')->nullOnDelete();
            $table->string('nome', 120);
            $table->string('telefone', 30);
            $table->string('titulo', 160);
            $table->date('data');
            $table->time('inicio');
            $table->time('fim');
            $table->foreignUuid('espaco_id')->nullable()->constrained('espacos')->nullOnDelete();
            $table->unsignedInteger('pessoas')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('status', 10)->index();
            $table->string('motivo_recusa', 200)->nullable();
            $table->foreignUuid('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacoes');
    }
};
