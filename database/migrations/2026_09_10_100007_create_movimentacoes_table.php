<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes', function (Blueprint $table) {
            $table->id();
            // exatamente um dos dois é preenchido
            $table->foreignId('insumo_id')->nullable()->constrained('insumos')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->cascadeOnDelete();
            // entrada | saida | perda | ajuste
            $table->string('tipo', 20);
            $table->decimal('quantidade', 12, 3);
            $table->decimal('custo_unitario', 12, 4)->default(0);
            $table->string('observacao')->nullable();
            // de onde veio o lançamento (Compra, Producao, Pedido...)
            $table->nullableMorphs('origem');
            $table->timestamps();

            $table->index(['insumo_id', 'created_at']);
            $table->index(['produto_id', 'created_at']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes');
    }
};
