<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('fornecedor');
            $table->date('data');
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observacoes')->nullable();
            // marcado quando o custo medio ja foi aplicado, evita lancar duas vezes
            $table->timestamp('aplicada_em')->nullable();
            $table->timestamps();

            $table->index('data');
        });

        Schema::create('compra_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            // já convertida para a unidade_base do insumo
            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_itens');
        Schema::dropIfExists('compras');
    }
};
