<?php

namespace App\Services;

use App\Enums\TipoMovimentacao;
use App\Models\Compra;
use App\Models\Insumo;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Entrada de insumo no estoque com recálculo do custo médio ponderado.
 *
 * Custo médio, nunca o último preço pago. Chocolate e manteiga oscilam demais:
 * usar o último preço faz a margem do mês pular sem que nada tenha mudado
 * na operação.
 */
class RegistrarCompra
{
    /**
     * Aplica a compra ao estoque. Idempotente: uma compra já aplicada é
     * ignorada, então re-salvar o formulário não lança o insumo duas vezes.
     */
    public function aplicar(Compra $compra): Compra
    {
        return DB::transaction(function () use ($compra) {
            // trava a própria compra para dois saves simultâneos não passarem juntos
            $compra = Compra::query()->lockForUpdate()->findOrFail($compra->id);

            if ($compra->jaAplicada()) {
                return $compra;
            }

            $compra->load('itens');
            $total = '0';

            foreach ($compra->itens as $item) {
                $insumo = Insumo::query()->lockForUpdate()->findOrFail($item->insumo_id);

                $this->somarAoEstoque($insumo, $item->quantidade, $item->valor_total);

                $insumo->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Entrada,
                    'quantidade' => Decimal::quantidade($item->quantidade),
                    'custo_unitario' => Decimal::custo(
                        Decimal::div($item->valor_total, $item->quantidade)
                    ),
                    'observacao' => 'Compra de '.$compra->fornecedor,
                    'origem_id' => $compra->id,
                    'origem_type' => Compra::class,
                ]);

                $total = Decimal::soma($total, $item->valor_total);
            }

            $compra->forceFill([
                'total' => Decimal::dinheiro($total),
                'aplicada_em' => now(),
            ])->save();

            return $compra;
        });
    }

    /**
     * Desfaz a compra: tira a quantidade e o valor do estoque, refazendo a
     * média com o que sobrou. Necessário para corrigir um lançamento errado.
     */
    public function estornar(Compra $compra): Compra
    {
        return DB::transaction(function () use ($compra) {
            $compra = Compra::query()->lockForUpdate()->findOrFail($compra->id);

            if (! $compra->jaAplicada()) {
                return $compra;
            }

            $compra->load('itens');

            foreach ($compra->itens as $item) {
                $insumo = Insumo::query()->lockForUpdate()->findOrFail($item->insumo_id);

                // mesma fórmula da entrada, com os sinais invertidos
                $this->somarAoEstoque(
                    $insumo,
                    Decimal::mul($item->quantidade, '-1'),
                    Decimal::mul($item->valor_total, '-1'),
                );

                $insumo->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Ajuste,
                    'quantidade' => Decimal::quantidade(Decimal::mul($item->quantidade, '-1')),
                    'custo_unitario' => Decimal::custo(
                        Decimal::div($item->valor_total, $item->quantidade)
                    ),
                    'observacao' => 'Estorno da compra de '.$compra->fornecedor,
                    'origem_id' => $compra->id,
                    'origem_type' => Compra::class,
                ]);
            }

            $compra->forceFill(['aplicada_em' => null])->save();

            return $compra;
        });
    }

    /**
     * O cálculo do CLAUDE.md, em decimal exato:
     *
     *   base         = estoque_atual > 0 ? estoque_atual : 0
     *   valorEstoque = base * custo_medio
     *   novaBase     = base + quantidade
     *   custo_medio  = novaBase > 0 e valor > 0
     *                    ? (valorEstoque + valor_total) / novaBase
     *                    : custo_medio anterior
     *
     * Só a parte positiva do estoque entra na média. O que está negativo já
     * foi para o forno pelo custo antigo; pesar isso de novo jogava o custo
     * médio para zero na compra seguinte. Sem base para calcular, fica o
     * último custo conhecido — zero faria a próxima fornada sair de graça.
     */
    private function somarAoEstoque(Insumo $insumo, mixed $quantidade, mixed $valorTotal): void
    {
        $base = Decimal::positivo($insumo->estoque_atual) ? (string) $insumo->estoque_atual : '0';
        $novaBase = Decimal::soma($base, $quantidade);
        $valor = Decimal::soma(Decimal::mul($base, $insumo->custo_medio), $valorTotal);

        $novoCusto = Decimal::positivo($novaBase) && Decimal::positivo($valor)
            ? Decimal::div($valor, $novaBase)
            : (string) $insumo->custo_medio;

        $insumo->forceFill([
            // o saldo segue o valor verdadeiro, inclusive se estava negativo
            'estoque_atual' => Decimal::quantidade(Decimal::soma($insumo->estoque_atual, $quantidade)),
            'custo_medio' => Decimal::custo($novoCusto),
        ])->save();
    }
}
