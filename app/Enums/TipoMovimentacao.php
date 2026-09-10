<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoMovimentacao: string implements HasColor, HasIcon, HasLabel
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Perda = 'perda';
    case Ajuste = 'ajuste';

    public function getLabel(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::Perda => 'Perda',
            self::Ajuste => 'Ajuste',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Entrada => 'success',
            self::Saida => 'info',
            self::Perda => 'danger',
            self::Ajuste => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Entrada => 'heroicon-m-arrow-down-tray',
            self::Saida => 'heroicon-m-arrow-up-tray',
            self::Perda => 'heroicon-m-trash',
            self::Ajuste => 'heroicon-m-adjustments-horizontal',
        };
    }
}
