<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'produtos';

    /**
     * custo_unitario NÃO está aqui de propósito: ele é calculado pela produção
     * a partir da ficha técnica e nunca deve chegar por request.
     */
    protected $fillable = [
        'nome', 'preco_venda', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'preco_venda' => 'decimal:2',
            'estoque_atual' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
            'ativo' => 'boolean',
        ];
    }

    public function receitas(): HasMany
    {
        return $this->hasMany(Receita::class);
    }

    public function pedidoItens(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** Margem unitária em reais: preço − custo congelado hoje. */
    public function margemUnitaria(): string
    {
        return bcsub((string) $this->preco_venda, (string) $this->custo_unitario, 4);
    }

    /** Margem em % sobre o preço de venda. Zero se o preço ainda não foi definido. */
    public function margemPercentual(): string
    {
        if (bccomp((string) $this->preco_venda, '0', 2) === 0) {
            return '0';
        }

        return bcmul(bcdiv($this->margemUnitaria(), (string) $this->preco_venda, 6), '100', 2);
    }
}
