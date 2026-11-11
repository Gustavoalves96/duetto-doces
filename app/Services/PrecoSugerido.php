<?php

namespace App\Services;

use App\Support\Decimal;

/**
 * Preço que cobre os insumos, os custos indiretos e a margem desejada.
 *
 *   preço = custo × (1 + indiretos%) ÷ (1 − margem%)
 *
 * A margem é sobre o preço de venda, igual à margem mostrada no produto.
 * É só uma sugestão: nada aqui é gravado nem entra em relatório.
 */
class PrecoSugerido
{
    /** Nulo quando ainda não há custo ou a margem pedida é impossível (100% ou mais). */
    public function calcular(mixed $custo): ?string
    {
        $margem = Decimal::div(config('confeitaria.margem_desejada_percentual'), '100');

        if (! Decimal::positivo($custo) || ! Decimal::menor($margem, '1')) {
            return null;
        }

        $comIndiretos = Decimal::soma(
            $custo,
            Decimal::percentual($custo, config('confeitaria.indiretos_percentual')),
        );

        return Decimal::dinheiro(Decimal::div($comIndiretos, Decimal::sub('1', $margem)));
    }

    /** Texto pronto para a tela: "R$ 4,40" ou travessão quando não dá para sugerir. */
    public function paraExibir(mixed $custo): string
    {
        $preco = $this->calcular($custo);

        return $preco === null ? '—' : Decimal::paraReal($preco);
    }

    /** De onde o número saiu, para ninguém tratar a sugestão como regra. */
    public function explicacao(): string
    {
        return 'Custo + '.Decimal::paraBrEnxuto(config('confeitaria.indiretos_percentual')).'% de gás, energia e afins, '
            .'para sobrar '.Decimal::paraBrEnxuto(config('confeitaria.margem_desejada_percentual')).'% do preço.';
    }
}
