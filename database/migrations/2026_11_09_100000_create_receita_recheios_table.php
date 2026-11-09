<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Só cria tabela nova: não mexe em nenhum dado existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // variação da ficha: parte da fornada leva um recheio e vira outro produto
        Schema::create('receita_recheios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receita_id')->constrained('receitas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            // shortcut: um insumo por recheio; recheio composto pede tabela de itens própria
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            // na unidade_base do insumo, para CADA unidade recheada
            $table->decimal('quantidade', 12, 3);
            $table->timestamps();

            $table->unique(['receita_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receita_recheios');
    }
};
