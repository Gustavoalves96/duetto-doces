<?php

namespace App\Models;

use App\Enums\UnidadeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insumo extends Model
{
    use HasFactory;

    protected $table = 'insumos';

    protected $fillable = [
        'nome', 'unidade_base', 'estoque_atual', 'custo_medio', 'estoque_minimo', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'unidade_base' => UnidadeBase::class,
            'estoque_atual' => 'decimal:3',
            'custo_medio' => 'decimal:4',
            'estoque_minimo' => 'decimal:3',
            'ativo' => 'boolean',
        ];
    }

    public function compraItens(): HasMany
    {
        return $this->hasMany(CompraItem::class);
    }

    public function receitaItens(): HasMany
    {
        return $this->hasMany(ReceitaItem::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** Estoque abaixo do mínimo configurado (inclui negativo). */
    public function scopeAbaixoDoMinimo(Builder $query): Builder
    {
        return $query->whereColumn('estoque_atual', '<=', 'estoque_minimo');
    }

    public function scopeNegativos(Builder $query): Builder
    {
        return $query->where('estoque_atual', '<', 0);
    }

    public function estaNegativo(): bool
    {
        return bccomp((string) $this->estoque_atual, '0', 3) === -1;
    }

    public function estaAbaixoDoMinimo(): bool
    {
        return bccomp((string) $this->estoque_atual, (string) $this->estoque_minimo, 3) <= 0;
    }

    /** Quanto de dinheiro está parado nesse insumo. */
    public function valorEmEstoque(): string
    {
        return bcmul((string) $this->estoque_atual, (string) $this->custo_medio, 2);
    }
}
