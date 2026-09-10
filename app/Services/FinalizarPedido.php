<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Enums\TipoMovimentacao;
use App\Models\Pedido;
use App\Models\Produto;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Efetivação da venda: congela o custo dos itens e baixa o produto acabado.
 *
 * O congelamento é a parte que não pode falhar. Se o relatório de margem
 * usasse produtos.custo_unitario na hora de ler, o resultado de março mudaria
 * sozinho toda vez que o preço do chocolate subisse em setembro.
 */
class FinalizarPedido
{
    /**
     * Efetiva o pedido: copia o custo atual de cada produto para o item e
     * baixa o estoque. Idempotente — pedido já efetivado não é tocado.
     */
    public function efetivar(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->jaBaixouEstoque()) {
                return $pedido;
            }

            $pedido->load('itens');

            foreach ($pedido->itens as $item) {
                $produto = Produto::query()->lockForUpdate()->findOrFail($item->produto_id);

                // CONGELA: a partir daqui esse número não muda mais
                $item->forceFill([
                    'custo_unitario' => Decimal::custo($produto->custo_unitario),
                ])->save();

                // estoque de produto pode ficar negativo pelo mesmo motivo do insumo:
                // às vezes a fornada sai e só é lançada depois da venda
                $produto->forceFill([
                    'estoque_atual' => Decimal::quantidade(
                        Decimal::sub($produto->estoque_atual, $item->quantidade)
                    ),
                ])->save();

                $produto->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Saida,
                    'quantidade' => Decimal::quantidade($item->quantidade),
                    'custo_unitario' => Decimal::custo($produto->custo_unitario),
                    'observacao' => 'Venda '.$pedido->identificacao,
                    'origem_id' => $pedido->id,
                    'origem_type' => Pedido::class,
                ]);
            }

            $pedido->forceFill(['baixado_em' => now()])->save();

            return $pedido;
        });
    }

    /**
     * Devolve o produto ao estoque. Usado no cancelamento e ao voltar um
     * pedido para orçamento. O custo congelado nos itens é mantido: ele é
     * histórico do que aconteceu, não estado atual.
     */
    public function estornar(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if (! $pedido->jaBaixouEstoque()) {
                return $pedido;
            }

            $pedido->load('itens');

            foreach ($pedido->itens as $item) {
                $produto = Produto::query()->lockForUpdate()->findOrFail($item->produto_id);

                $produto->forceFill([
                    'estoque_atual' => Decimal::quantidade(
                        Decimal::soma($produto->estoque_atual, $item->quantidade)
                    ),
                ])->save();

                $produto->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Entrada,
                    'quantidade' => Decimal::quantidade($item->quantidade),
                    'custo_unitario' => Decimal::custo($item->custo_unitario),
                    'observacao' => 'Estorno da venda '.$pedido->identificacao,
                    'origem_id' => $pedido->id,
                    'origem_type' => Pedido::class,
                ]);
            }

            $pedido->forceFill(['baixado_em' => null])->save();

            return $pedido;
        });
    }

    /**
     * Troca o status e dispara o efeito colateral certo:
     * sair de orçamento efetiva a venda, cancelar devolve o produto.
     */
    public function mudarStatus(Pedido $pedido, StatusPedido $novo): Pedido
    {
        return DB::transaction(function () use ($pedido, $novo) {
            $pedido->forceFill(['status' => $novo])->save();

            if ($novo->contabiliza()) {
                return $this->efetivar($pedido)->refresh();
            }

            // voltou para orçamento ou foi cancelado: produto volta para a prateleira
            return $this->estornar($pedido)->refresh();
        });
    }

    /**
     * Sincroniza o pedido depois de salvar o formulário.
     * Um pedido que já nasce confirmado (venda avulsa no balcão) é efetivado
     * na hora; orçamento fica só registrado, sem mexer em estoque.
     */
    public function sincronizar(Pedido $pedido): Pedido
    {
        $pedido->refresh()->load('itens');

        if ($pedido->status->contabiliza() && ! $pedido->jaBaixouEstoque()) {
            return $this->efetivar($pedido)->refresh();
        }

        if (! $pedido->status->contabiliza() && $pedido->jaBaixouEstoque()) {
            return $this->estornar($pedido)->refresh();
        }

        return $pedido;
    }
}
