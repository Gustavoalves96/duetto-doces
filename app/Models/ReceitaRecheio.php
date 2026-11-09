<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Parte da fornada que leva recheio e sai como outro produto (ex.: com Nutella). */
class ReceitaRecheio extends Model
{
    protected $table = 'receita_recheios';

    protected $fillable = ['receita_id', 'produto_id', 'insumo_id', 'quantidade'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:3'];
    }

    public function receita(): BelongsTo
    {
        return $this->belongsTo(Receita::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
