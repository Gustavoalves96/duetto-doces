<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Insumo NÃO entra aqui — insumo entra por compra e vira CMV via produção.
 * Despesa é o custo de manter a operação de pé.
 */
enum CategoriaDespesa: string implements HasColor, HasLabel
{
    case ProLabore = 'pro_labore';
    case Embalagem = 'embalagem';
    case Aluguel = 'aluguel';
    case Energia = 'energia';
    case Agua = 'agua';
    case Gas = 'gas';
    case Internet = 'internet';
    case Transporte = 'transporte';
    case Marketing = 'marketing';
    case Equipamento = 'equipamento';
    case Manutencao = 'manutencao';
    case Impostos = 'impostos';
    case Outros = 'outros';

    public function getLabel(): string
    {
        return match ($this) {
            self::ProLabore => 'Pró-labore',
            self::Embalagem => 'Embalagem',
            self::Aluguel => 'Aluguel',
            self::Energia => 'Energia',
            self::Agua => 'Água',
            self::Gas => 'Gás',
            self::Internet => 'Internet / telefone',
            self::Transporte => 'Transporte / entrega',
            self::Marketing => 'Marketing',
            self::Equipamento => 'Equipamento',
            self::Manutencao => 'Manutenção',
            self::Impostos => 'Impostos e taxas',
            self::Outros => 'Outros',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ProLabore => 'success',
            self::Embalagem, self::Transporte => 'info',
            self::Impostos => 'danger',
            self::Outros => 'gray',
            default => 'warning',
        };
    }
}
