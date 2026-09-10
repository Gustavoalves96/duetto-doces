<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Producao extends Model
{
    use HasFactory;

    // "producaos" seria o plural automático; a tabela é producoes
    protected $table = 'producoes';

    protected $fillable = [
        'receita_id', 'data', 'multiplicador', 'quantidade_produzida', 'custo_total', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'multiplicador' => 'decimal:3',
            'quantidade_produzida' => 'decimal:3',
            'custo_total' => 'decimal:2',
        ];
    }

    public function receita(): BelongsTo
    {
        return $this->belongsTo(Receita::class);
    }

    public function movimentacoes(): MorphMany
    {
        return $this->morphMany(Movimentacao::class, 'origem');
    }

    /** Custo real por unidade deste lote. */
    public function custoUnitarioLote(): string
    {
        if (bccomp((string) $this->quantidade_produzida, '0', 3) === 0) {
            return '0';
        }

        return bcdiv((string) $this->custo_total, (string) $this->quantidade_produzida, 4);
    }
}
