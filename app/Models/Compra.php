<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = ['fornecedor', 'data', 'total', 'observacoes'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'total' => 'decimal:2',
            'aplicada_em' => 'datetime',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CompraItem::class);
    }

    /** O custo medio ja foi aplicado aos insumos desta compra? */
    public function jaAplicada(): bool
    {
        return $this->aplicada_em !== null;
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'origem_id')
            ->where('origem_type', self::class);
    }
}
