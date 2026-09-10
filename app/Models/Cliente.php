<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = ['nome', 'telefone', 'observacoes'];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /** Telefone gravado só com dígitos; formata na leitura. */
    public function telefoneFormatado(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $this->telefone);

        return match (strlen((string) $digitos)) {
            11 => sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7)),
            10 => sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6)),
            default => $this->telefone ?: null,
        };
    }
}
