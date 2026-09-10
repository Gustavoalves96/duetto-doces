<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('nome')->nullable();
            // quantas unidades do produto uma fornada (multiplicador 1) rende
            $table->decimal('rendimento', 12, 3);
            $table->text('observacoes')->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('receita_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receita_id')->constrained('receitas')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            // na unidade_base do insumo, para uma fornada
            $table->decimal('quantidade', 12, 3);
            $table->timestamps();

            $table->unique(['receita_id', 'insumo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receita_itens');
        Schema::dropIfExists('receitas');
    }
};
