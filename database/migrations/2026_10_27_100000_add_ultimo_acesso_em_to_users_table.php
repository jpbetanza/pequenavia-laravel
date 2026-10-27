<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Última vez que a pessoa abriu o app logada (no máximo uma gravação por hora).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ultimo_acesso_em')->nullable();
        });
    }

    /**
     * Remove o registro do último acesso.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ultimo_acesso_em');
        });
    }
};
