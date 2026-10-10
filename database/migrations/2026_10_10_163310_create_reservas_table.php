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
        Schema::create('reservas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('espaco_id')->constrained('espacos')->deferrable()->initiallyImmediate(false);
            $table->foreignUuid('grupo_id')->nullable()->constrained('grupos')->nullOnDelete();
            $table->string('titulo', 160);
            $table->date('data');
            $table->time('inicio');
            $table->time('fim');
            $table->unsignedInteger('pessoas')->nullable();
            $table->text('observacoes')->nullable();
            $table->uuid('serie_id')->nullable()->index();
            $table->uuid('solicitacao_id')->nullable();
            $table->timestamps();

            $table->index(['espaco_id', 'data']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
