<?php

namespace App\Support;

/**
 * Aritmética decimal exata sobre bcmath.
 *
 * Todo cálculo de dinheiro, quantidade e custo passa por aqui. Nunca use
 * operadores nativos (+, *, /) em valor monetário: 0.1 + 0.2 em float dá
 * 0.30000000000000004, e num CMV acumulado isso vira centavo perdido que
 * ninguém consegue rastrear no fechamento do mês.
 *
 * Valores trafegam como string. Escalas usadas no schema:
 *   dinheiro   -> 2 casas
 *   quantidade -> 3 casas
 *   custo      -> 4 casas
 */
final class Decimal
{
    public const DINHEIRO = 2;

    public const QUANTIDADE = 3;

    public const CUSTO = 4;

    /** Escala interna de trabalho, folgada para não perder precisão no meio. */
    private const INTERNA = 10;

    /** Normaliza qualquer entrada (null, int, float, string com vírgula) para string decimal. */
    public static function de(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '0';
        }

        if (is_string($valor)) {
            $valor = trim($valor);

            // aceita "1.234,56" (pt-BR) e "1234.56"
            if (str_contains($valor, ',')) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            }
        }

        if (is_float($valor)) {
            // única fronteira em que float é tolerado: entrada externa.
            // convertido para string com precisão suficiente e nunca reusado como float.
            $valor = number_format($valor, self::INTERNA, '.', '');
        }

        $valor = (string) $valor;

        return is_numeric($valor) ? $valor : '0';
    }

    public static function soma(mixed $a, mixed $b, int $escala = self::INTERNA): string
    {
        return bcadd(self::de($a), self::de($b), $escala);
    }

    public static function sub(mixed $a, mixed $b, int $escala = self::INTERNA): string
    {
        return bcsub(self::de($a), self::de($b), $escala);
    }

    public static function mul(mixed $a, mixed $b, int $escala = self::INTERNA): string
    {
        return bcmul(self::de($a), self::de($b), $escala);
    }

    /** Divisão protegida: divisor zero devolve '0' em vez de estourar. */
    public static function div(mixed $a, mixed $b, int $escala = self::INTERNA): string
    {
        $b = self::de($b);

        if (self::ehZero($b)) {
            return '0';
        }

        return bcdiv(self::de($a), $b, $escala);
    }

    /**
     * Arredonda meio-para-cima. bcmath só trunca, então somamos meia unidade
     * da última casa antes de cortar.
     */
    public static function arredonda(mixed $valor, int $escala = self::DINHEIRO): string
    {
        $valor = self::de($valor);
        // meia unidade da última casa: escala 2 -> 0.005
        $meio = bcdiv('5', bcpow('10', (string) ($escala + 1)), $escala + 1);

        $ajustado = self::negativo($valor)
            ? bcsub($valor, $meio, $escala + 1)
            : bcadd($valor, $meio, $escala + 1);

        return bcadd($ajustado, '0', $escala);
    }

    public static function dinheiro(mixed $valor): string
    {
        return self::arredonda($valor, self::DINHEIRO);
    }

    public static function quantidade(mixed $valor): string
    {
        return self::arredonda($valor, self::QUANTIDADE);
    }

    public static function custo(mixed $valor): string
    {
        return self::arredonda($valor, self::CUSTO);
    }

    /** -1, 0 ou 1 */
    public static function compara(mixed $a, mixed $b, int $escala = self::INTERNA): int
    {
        return bccomp(self::de($a), self::de($b), $escala);
    }

    public static function maior(mixed $a, mixed $b): bool
    {
        return self::compara($a, $b) === 1;
    }

    public static function menor(mixed $a, mixed $b): bool
    {
        return self::compara($a, $b) === -1;
    }

    public static function ehZero(mixed $valor): bool
    {
        return self::compara($valor, '0') === 0;
    }

    public static function positivo(mixed $valor): bool
    {
        return self::compara($valor, '0') === 1;
    }

    public static function negativo(mixed $valor): bool
    {
        return self::compara($valor, '0') === -1;
    }

    /** Soma uma lista de valores. */
    public static function somaLista(iterable $valores, int $escala = self::INTERNA): string
    {
        $total = '0';

        foreach ($valores as $valor) {
            $total = bcadd($total, self::de($valor), $escala);
        }

        return $total;
    }

    /** Percentual: quanto é $percentual% de $valor. */
    public static function percentual(mixed $valor, mixed $percentual): string
    {
        return self::div(self::mul($valor, $percentual), '100');
    }

    /** Formata para exibição em pt-BR, sem símbolo. Ex.: 1234.5 -> "1.234,50" */
    public static function paraBr(mixed $valor, int $escala = self::DINHEIRO): string
    {
        $valor = self::arredonda($valor, $escala);

        return number_format((float) $valor, $escala, ',', '.');
    }

    /** Formata como moeda brasileira. Ex.: "R$ 1.234,50" */
    public static function paraReal(mixed $valor): string
    {
        return 'R$ '.self::paraBr($valor, self::DINHEIRO);
    }
}
