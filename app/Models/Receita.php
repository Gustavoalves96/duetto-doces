<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receita extends Model
{
    use HasFactory;

    protected $table = 'receitas';

    protected $fillable = ['produto_id', 'nome', 'rendimento', 'observacoes', 'ativa'];

    protected function casts(): array
    {
        return [
            'rendimento' => 'decimal:3',
            'ativa' => 'boolean',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ReceitaItem::class);
    }

    public function producoes(): HasMany
    {
        return $this->hasMany(Producao::class);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }

    public function getNomeExibicaoAttribute(): string
    {
        return $this->nome ?: ('Ficha de '.($this->produto?->nome ?? 'produto'));
    }

    /**
     * Custo estimado de uma fornada com os custos médios de HOJE.
     * É uma projeção para a tela; o custo real é gravado pela produção.
     */
    public function custoEstimadoFornada(): string
    {
        $total = '0';

        foreach ($this->itens as $item) {
            $total = bcadd($total, bcmul((string) $item->quantidade, (string) $item->insumo->custo_medio, 6), 6);
        }

        return bcadd($total, '0', 2);
    }

    /** Custo estimado por unidade produzida. */
    public function custoEstimadoUnitario(): string
    {
        if (bccomp((string) $this->rendimento, '0', 3) === 0) {
            return '0';
        }

        return bcdiv($this->custoEstimadoFornada(), (string) $this->rendimento, 4);
    }
}
