<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoPedido: string implements HasColor, HasLabel
{
    case Encomenda = 'encomenda';
    case Avulsa = 'avulsa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Encomenda => 'Encomenda',
            self::Avulsa => 'Venda avulsa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Encomenda => 'warning',
            self::Avulsa => 'info',
        };
    }
}
