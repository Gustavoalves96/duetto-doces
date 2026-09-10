<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->decimal('preco_venda', 12, 2)->default(0);
            $table->decimal('estoque_atual', 12, 3)->default(0);
            // NUNCA preenchido à mão: sai da produção, via ficha técnica.
            $table->decimal('custo_unitario', 12, 4)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
