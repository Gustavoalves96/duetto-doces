<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despesas', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 30);
            $table->string('descricao');
            $table->decimal('valor', 12, 2);
            $table->date('data');
            // despesa atrelada a um pedido (frete de uma entrega específica)
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->boolean('recorrente')->default(false);
            $table->timestamps();

            $table->index(['data', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas');
    }
};
