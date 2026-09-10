<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receita_id')->constrained('receitas')->restrictOnDelete();
            $table->date('data');
            // 0.5 = meia fornada, 3 = três fornadas
            $table->decimal('multiplicador', 12, 3)->default(1);
            $table->decimal('quantidade_produzida', 12, 3);
            // soma de (quantidade baixada * custo_medio do insumo no momento)
            $table->decimal('custo_total', 12, 2);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producoes');
    }
};
