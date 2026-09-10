<?php

namespace App\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'cliente_id', 'tipo', 'status', 'data', 'data_entrega', 'desconto',
        'sinal_pago', 'forma_pagamento', 'taxa_operadora', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPedido::class,
            'status' => StatusPedido::class,
            'forma_pagamento' => FormaPagamento::class,
            'data' => 'date',
            'data_entrega' => 'date',
            'desconto' => 'decimal:2',
            'sinal_pago' => 'decimal:2',
            'taxa_operadora' => 'decimal:2',
            'baixado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    public function movimentacoes(): MorphMany
    {
        return $this->morphMany(Movimentacao::class, 'origem');
    }

    /** Pedidos que entram no faturamento: tudo menos orçamento e cancelado. */
    public function scopeContabilizados(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusPedido::Orcamento->value,
            StatusPedido::Cancelado->value,
        ]);
    }

    public function scopeNoPeriodo(Builder $query, string $de, string $ate): Builder
    {
        // whereDate ignora a hora que o cast 'date' grava junto
        return $query->whereDate('data', '>=', $de)->whereDate('data', '<=', $ate);
    }

    /** Encomendas ainda não entregues nem canceladas. */
    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('tipo', TipoPedido::Encomenda->value)
            ->whereNotIn('status', [
                StatusPedido::Entregue->value,
                StatusPedido::Cancelado->value,
            ]);
    }

    /** Soma dos itens, antes de desconto. */
    public function subtotal(): string
    {
        $total = '0';

        foreach ($this->itens as $item) {
            $total = bcadd($total, $item->totalLinha(), 4);
        }

        return bcadd($total, '0', 2);
    }

    /** O que o cliente paga: subtotal − desconto. */
    public function total(): string
    {
        return bcsub($this->subtotal(), (string) $this->desconto, 2);
    }

    /** O que sobra na conta depois da maquininha. */
    public function faturamentoLiquido(): string
    {
        return bcsub($this->total(), (string) $this->taxa_operadora, 2);
    }

    /** Custo da mercadoria vendida, com os custos CONGELADOS nos itens. */
    public function cmv(): string
    {
        $total = '0';

        foreach ($this->itens as $item) {
            $total = bcadd($total, $item->custoLinha(), 4);
        }

        return bcadd($total, '0', 2);
    }

    /** Faturamento líquido − CMV. */
    public function margemContribuicao(): string
    {
        return bcsub($this->faturamentoLiquido(), $this->cmv(), 2);
    }

    public function saldoAReceber(): string
    {
        return bcsub($this->total(), (string) $this->sinal_pago, 2);
    }

    /** O estoque de produto já foi baixado por este pedido? */
    public function jaBaixouEstoque(): bool
    {
        return $this->baixado_em !== null;
    }

    public function getIdentificacaoAttribute(): string
    {
        return '#'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
