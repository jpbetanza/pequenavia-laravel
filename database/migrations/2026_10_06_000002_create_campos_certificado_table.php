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
        Schema::create('campos_certificado', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('modelo_id')->constrained('modelos_certificado')->cascadeOnDelete();
            $table->string('nome');
            $table->string('label')->nullable();
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->string('cor', 7)->default('#000000');
            $table->unsignedSmallInteger('tamanho_fonte')->default(32);
            $table->string('fonte_familia')->default('Georgia');
            $table->unsignedSmallInteger('peso_fonte')->default(600);
            $table->decimal('espacamento_letras', 5, 2)->default(0);
            $table->boolean('centralizado')->default(false);
            $table->timestamps();

            $table->unique(['modelo_id', 'nome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campos_certificado');
    }
};
