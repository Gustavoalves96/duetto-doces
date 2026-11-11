<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Só acrescenta: nenhuma coluna existente muda e nenhum dado é reescrito.
 * Produções antigas ficam com o campo vazio, ou seja, valendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producoes', function (Blueprint $table) {
            // marcado quando a produção foi desfeita, evita estornar duas vezes
            $table->timestamp('estornada_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('producoes', function (Blueprint $table) {
            $table->dropColumn('estornada_em');
        });
    }
};
