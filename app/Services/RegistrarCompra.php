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
     *   valorEstoque = estoque_atual * custo_medio
     *   novaQtd      = estoque_atual + quantidade
     *   custo_medio  = novaQtd > 0 ? (valorEstoque + valor_total) / novaQtd : 0
     */
    private function somarAoEstoque(Insumo $insumo, mixed $quantidade, mixed $valorTotal): void
    {
        $valorEstoque = Decimal::mul($insumo->estoque_atual, $insumo->custo_medio);
        $novaQtd = Decimal::soma($insumo->estoque_atual, $quantidade);

        $novoCusto = Decimal::positivo($novaQtd)
            ? Decimal::div(Decimal::soma($valorEstoque, $valorTotal), $novaQtd)
            : '0';

        $insumo->forceFill([
            'estoque_atual' => Decimal::quantidade($novaQtd),
            // estoque negativo não pode arrastar o custo médio para baixo de zero
            'custo_medio' => Decimal::negativo($novoCusto) ? '0' : Decimal::custo($novoCusto),
        ])->save();
    }
}
