<?php

namespace App\Models;

use App\Enums\TipoMovimentacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Movimentacao extends Model
{
    use HasFactory;

    protected $table = 'movimentacoes';

    protected $fillable = [
        'insumo_id', 'produto_id', 'tipo', 'quantidade', 'custo_unitario', 'observacao',
        'origem_id', 'origem_type',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacao::class,
            'quantidade' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
        ];
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function origem(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDeInsumos(Builder $query): Builder
    {
        return $query->whereNotNull('insumo_id');
    }

    public function scopeDeProdutos(Builder $query): Builder
    {
        return $query->whereNotNull('produto_id');
    }

    public function scopePerdas(Builder $query): Builder
    {
        return $query->where('tipo', TipoMovimentacao::Perda);
    }

    /** Nome do item movimentado, seja insumo ou produto. */
    public function getItemNomeAttribute(): string
    {
        return $this->insumo?->nome ?? $this->produto?->nome ?? '—';
    }

    /** Valor financeiro da movimentação. */
    public function valor(): string
    {
        return bcmul((string) $this->quantidade, (string) $this->custo_unitario, 2);
    }
}
