<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraItem extends Model
{
    use HasFactory;

    protected $table = 'compra_itens';

    protected $fillable = ['compra_id', 'insumo_id', 'quantidade', 'valor_total'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_total' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    /** Preço pago por unidade base nesta compra. Só informativo. */
    public function custoUnitario(): string
    {
        if (bccomp((string) $this->quantidade, '0', 3) === 0) {
            return '0';
        }

        return bcdiv((string) $this->valor_total, (string) $this->quantidade, 4);
    }
}
