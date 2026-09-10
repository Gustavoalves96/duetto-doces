<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            // venda avulsa no balcão não precisa de cliente
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('tipo', 20);
            $table->string('status', 20)->default('orcamento');
            $table->date('data');
            $table->date('data_entrega')->nullable();
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('sinal_pago', 12, 2)->default(0);
            $table->string('forma_pagamento', 20)->nullable();
            // taxa da maquininha/delivery: sai do faturamento líquido
            $table->decimal('taxa_operadora', 12, 2)->default(0);
            $table->text('observacoes')->nullable();
            // marcado quando o estoque de produto foi baixado, evita baixa dupla
            $table->timestamp('baixado_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'data']);
            $table->index('data_entrega');
        });

        Schema::create('pedido_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('preco_unitario', 12, 2);
            // CONGELADO na venda. Nunca recalcule com o custo atual.
            $table->decimal('custo_unitario', 12, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_itens');
        Schema::dropIfExists('pedidos');
    }
};
