<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceitaItem extends Model
{
    use HasFactory;

    protected $table = 'receita_itens';

    protected $fillable = ['receita_id', 'insumo_id', 'quantidade'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:3'];
    }

    public function receita(): BelongsTo
    {
        return $this->belongsTo(Receita::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
