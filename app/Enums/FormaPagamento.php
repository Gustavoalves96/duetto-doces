<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum FormaPagamento: string implements HasIcon, HasLabel
{
    case Dinheiro = 'dinheiro';
    case Pix = 'pix';
    case Debito = 'debito';
    case Credito = 'credito';
    case Transferencia = 'transferencia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::Pix => 'Pix',
            self::Debito => 'Cartão de débito',
            self::Credito => 'Cartão de crédito',
            self::Transferencia => 'Transferência',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Dinheiro => 'heroicon-m-banknotes',
            self::Pix => 'heroicon-m-bolt',
            self::Debito, self::Credito => 'heroicon-m-credit-card',
            self::Transferencia => 'heroicon-m-building-library',
        };
    }

    /**
     * Taxa típica da operadora, em percentual. Serve só para sugerir o valor
     * no formulário — o que vale no relatório é pedidos.taxa_operadora.
     */
    public function taxaSugerida(): string
    {
        return match ($this) {
            self::Dinheiro, self::Pix, self::Transferencia => '0',
            self::Debito => '1.99',
            self::Credito => '4.99',
        };
    }
}
