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
        Schema::create('compartilhamentos_modelo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('modelo_id')->constrained('modelos_certificado')->cascadeOnDelete();
            $table->string('email');
            $table->boolean('pode_editar')->default(false);
            $table->timestamps();

            $table->unique(['modelo_id', 'email']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compartilhamentos_modelo');
    }
};
