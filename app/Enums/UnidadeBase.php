<?php

namespace App\Enums;

use App\Support\Decimal;
use Filament\Support\Contracts\HasLabel;

/**
 * Unidade em que o insumo é ARMAZENADO. Sempre a menor unidade:
 * compra em kg vira g na entrada, litro vira ml. Nunca converta no cálculo.
 */
enum UnidadeBase: string implements HasLabel
{
    case Grama = 'g';
    case Mililitro = 'ml';
    case Unidade = 'un';

    /**
     * Resolve o valor vindo de um formulário: dependendo do momento do ciclo
     * de vida, o Filament devolve a string crua OU o enum já convertido.
     */
    public static function resolver(mixed $valor): ?self
    {
        if ($valor instanceof self) {
            return $valor;
        }

        if ($valor === null || $valor === '') {
            return null;
        }

        return self::tryFrom((string) $valor);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Grama => 'Grama (g)',
            self::Mililitro => 'Mililitro (ml)',
            self::Unidade => 'Unidade (un)',
        };
    }

    /** Sufixo curto usado em tabelas e campos. */
    public function sufixo(): string
    {
        return $this->value;
    }

    /**
     * Fator de conversão para a unidade de compra mais comum.
     * kg -> g e L -> ml são 1000; unidade é 1.
     */
    public function fatorCompra(): int
    {
        return match ($this) {
            self::Grama, self::Mililitro => 1000,
            self::Unidade => 1,
        };
    }

    /**
     * Unidades aceitas no lançamento de compra, da maior para a menor.
     * A confeitaria compra em kg e a ficha usa g — a conversão é aqui, na
     * entrada, e nunca no meio de um cálculo.
     *
     * @return array<string, string>
     */
    public function opcoesEntrada(): array
    {
        return match ($this) {
            self::Grama => ['kg' => 'Quilo (kg)', 'g' => 'Grama (g)'],
            self::Mililitro => ['l' => 'Litro (L)', 'ml' => 'Mililitro (ml)'],
            self::Unidade => ['un' => 'Unidade (un)'],
        };
    }

    /** Quantos da unidade base cabem em 1 da unidade informada. */
    public function fatorDe(string $unidadeEntrada): string
    {
        return match (strtolower($unidadeEntrada)) {
            'kg', 'l' => '1000',
            default => '1',
        };
    }

    /** Converte a quantidade informada para a unidade base. */
    public function paraBase(mixed $quantidade, string $unidadeEntrada): string
    {
        return bcmul(
            Decimal::de($quantidade),
            $this->fatorDe($unidadeEntrada),
            3
        );
    }

    /** Rótulo da unidade de compra correspondente (kg, L, un). */
    public function unidadeCompra(): string
    {
        return match ($this) {
            self::Grama => 'kg',
            self::Mililitro => 'L',
            self::Unidade => 'un',
        };
    }
}
