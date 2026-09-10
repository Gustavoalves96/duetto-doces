<?php

namespace App\Services;

use App\Enums\TipoMovimentacao;
use App\Models\Insumo;
use App\Models\Movimentacao;
use App\Models\Produto;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Perdas e ajustes de inventário.
 *
 * Fornada queimada, insumo vencido, saco de farinha que rasgou. Sem uma tela
 * para isso o estoque para de bater com a prateleira em duas semanas e o
 * sistema inteiro perde a credibilidade.
 *
 * Perda NÃO mexe no custo médio: o que estragou custou o que custou. Mudar a
 * média aqui esconderia o prejuízo dentro do custo do produto.
 */
class MovimentarEstoque
{
    public function perdaDeInsumo(Insumo $insumo, string $quantidade, ?string $observacao = null): Movimentacao
    {
        $quantidade = $this->quantidadePositiva($quantidade);

        return DB::transaction(function () use ($insumo, $quantidade, $observacao) {
            $insumo = Insumo::query()->lockForUpdate()->findOrFail($insumo->id);

            $insumo->forceFill([
                'estoque_atual' => Decimal::quantidade(
                    Decimal::sub($insumo->estoque_atual, $quantidade)
                ),
            ])->save();

            return $insumo->movimentacoes()->create([
                'tipo' => TipoMovimentacao::Perda,
                'quantidade' => $quantidade,
                'custo_unitario' => Decimal::custo($insumo->custo_medio),
                'observacao' => $observacao,
            ]);
        });
    }

    public function perdaDeProduto(Produto $produto, string $quantidade, ?string $observacao = null): Movimentacao
    {
        $quantidade = $this->quantidadePositiva($quantidade);

        return DB::transaction(function () use ($produto, $quantidade, $observacao) {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produto->id);

            $produto->forceFill([
                'estoque_atual' => Decimal::quantidade(
                    Decimal::sub($produto->estoque_atual, $quantidade)
                ),
            ])->save();

            return $produto->movimentacoes()->create([
                'tipo' => TipoMovimentacao::Perda,
                'quantidade' => $quantidade,
                'custo_unitario' => Decimal::custo($produto->custo_unitario),
                'observacao' => $observacao,
            ]);
        });
    }

    /**
     * Contagem de inventário: informa o saldo real e o sistema grava a diferença.
     * A movimentação guarda o delta, positivo ou negativo.
     */
    public function ajustarInsumo(Insumo $insumo, string $saldoReal, ?string $observacao = null): Movimentacao
    {
        return DB::transaction(function () use ($insumo, $saldoReal, $observacao) {
            $insumo = Insumo::query()->lockForUpdate()->findOrFail($insumo->id);

            $saldoReal = Decimal::quantidade($saldoReal);
            $delta = Decimal::sub($saldoReal, $insumo->estoque_atual);

            $insumo->forceFill(['estoque_atual' => $saldoReal])->save();

            return $insumo->movimentacoes()->create([
                'tipo' => TipoMovimentacao::Ajuste,
                'quantidade' => Decimal::quantidade($delta),
                'custo_unitario' => Decimal::custo($insumo->custo_medio),
                'observacao' => $observacao ?? 'Contagem de inventário',
            ]);
        });
    }

    public function ajustarProduto(Produto $produto, string $saldoReal, ?string $observacao = null): Movimentacao
    {
        return DB::transaction(function () use ($produto, $saldoReal, $observacao) {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produto->id);

            $saldoReal = Decimal::quantidade($saldoReal);
            $delta = Decimal::sub($saldoReal, $produto->estoque_atual);

            $produto->forceFill(['estoque_atual' => $saldoReal])->save();

            return $produto->movimentacoes()->create([
                'tipo' => TipoMovimentacao::Ajuste,
                'quantidade' => Decimal::quantidade($delta),
                'custo_unitario' => Decimal::custo($produto->custo_unitario),
                'observacao' => $observacao ?? 'Contagem de inventário',
            ]);
        });
    }

    private function quantidadePositiva(string $quantidade): string
    {
        $quantidade = Decimal::quantidade($quantidade);

        if (! Decimal::positivo($quantidade)) {
            throw new InvalidArgumentException('A quantidade precisa ser maior que zero.');
        }

        return $quantidade;
    }
}
