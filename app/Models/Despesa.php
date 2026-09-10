<?php

namespace App\Models;

use App\Enums\CategoriaDespesa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Despesa extends Model
{
    use HasFactory;

    protected $table = 'despesas';

    protected $fillable = [
        'categoria', 'descricao', 'valor', 'data', 'pedido_id', 'recorrente',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaDespesa::class,
            'valor' => 'decimal:2',
            'data' => 'date',
            'recorrente' => 'boolean',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function scopeNoPeriodo(Builder $query, string $de, string $ate): Builder
    {
        // whereDate ignora a hora que o cast 'date' grava junto
        return $query->whereDate('data', '>=', $de)->whereDate('data', '<=', $ate);
    }

    public function scopeRecorrentes(Builder $query): Builder
    {
        return $query->where('recorrente', true);
    }
}
