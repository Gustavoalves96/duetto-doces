<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusPedido: string implements HasColor, HasIcon, HasLabel
{
    case Orcamento = 'orcamento';
    case Confirmado = 'confirmado';
    case EmProducao = 'em_producao';
    case Pronto = 'pronto';
    case Entregue = 'entregue';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Orcamento => 'Orçamento',
            self::Confirmado => 'Confirmado',
            self::EmProducao => 'Em produção',
            self::Pronto => 'Pronto',
            self::Entregue => 'Entregue',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Orcamento => 'gray',
            self::Confirmado => 'info',
            self::EmProducao => 'warning',
            self::Pronto => 'primary',
            self::Entregue => 'success',
            self::Cancelado => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Orcamento => 'heroicon-m-document-text',
            self::Confirmado => 'heroicon-m-check-circle',
            self::EmProducao => 'heroicon-m-fire',
            self::Pronto => 'heroicon-m-shopping-bag',
            self::Entregue => 'heroicon-m-truck',
            self::Cancelado => 'heroicon-m-x-circle',
        };
    }

    /** Próximo status do fluxo normal, ou null se não houver. */
    public function proximo(): ?self
    {
        return match ($this) {
            self::Orcamento => self::Confirmado,
            self::Confirmado => self::EmProducao,
            self::EmProducao => self::Pronto,
            self::Pronto => self::Entregue,
            self::Entregue, self::Cancelado => null,
        };
    }

    /** Estados finais: não avançam nem podem ser cancelados de novo. */
    public function finalizado(): bool
    {
        return in_array($this, [self::Entregue, self::Cancelado], true);
    }

    /**
     * A venda conta para faturamento? Orçamento ainda não é venda,
     * cancelado nunca foi. Os demais já comprometem produto.
     */
    public function contabiliza(): bool
    {
        return ! in_array($this, [self::Orcamento, self::Cancelado], true);
    }
}
