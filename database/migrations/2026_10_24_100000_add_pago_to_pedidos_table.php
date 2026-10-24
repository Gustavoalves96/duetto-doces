<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Só acrescenta: nenhuma coluna existente muda e nenhum dado é reescrito.
 * Pedidos antigos ficam como "não pago" até alguém marcar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // encomenda quitada? Venda avulsa é paga no balcão e não usa este campo
            $table->boolean('pago')->default(false)->after('sinal_pago');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('pago');
        });
    }
};
