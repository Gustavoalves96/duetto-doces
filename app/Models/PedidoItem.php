<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    use HasFactory;

    protected $table = 'pedido_itens';

    /**
     * custo_unitario fica fora do fillable: quem grava é o serviço de venda,
     * copiando produtos.custo_unitario no instante da venda. Se viesse do
     * formulário, o relatório do mês passado mudaria sozinho.
     */
    protected $fillable = ['pedido_id', 'produto_id', 'quantidade', 'preco_unitario'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:2',
            'custo_unitario' => 'decimal:4',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function totalLinha(): string
    {
        return bcmul((string) $this->quantidade, (string) $this->preco_unitario, 4);
    }

    public function custoLinha(): string
    {
        return bcmul((string) $this->quantidade, (string) $this->custo_unitario, 4);
    }

    public function margemLinha(): string
    {
        return bcsub($this->totalLinha(), $this->custoLinha(), 4);
    }
}
