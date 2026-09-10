<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            // g | ml | un — sempre a menor unidade
            $table->string('unidade_base', 4);
            $table->decimal('estoque_atual', 12, 3)->default(0);
            // 4 casas: o custo por grama costuma ser fração de centavo
            $table->decimal('custo_medio', 12, 4)->default(0);
            $table->decimal('estoque_minimo', 12, 3)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
