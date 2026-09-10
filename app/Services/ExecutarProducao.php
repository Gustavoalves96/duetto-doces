<?php

namespace App\Services;

use App\Enums\TipoMovimentacao;
use App\Models\Insumo;
use App\Models\Producao;
use App\Models\Produto;
use App\Models\Receita;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Transforma insumo em produto acabado usando a ficha técnica.
 *
 * É aqui que o custo do brownie aparece. Ele nunca é digitado: sai da soma
 * dos insumos baixados, pelo custo médio do momento da fornada.
 */
class ExecutarProducao
{
    /**
     * @param  string  $multiplicador  0.5 = meia fornada, 3 = três fornadas.
     */
    public function executar(
        Receita $receita,
        string $multiplicador = '1',
        ?string $data = null,
        ?string $observacoes = null,
    ): Producao {
        $multiplicador = Decimal::quantidade($multiplicador);

        if (! Decimal::positivo($multiplicador)) {
            throw new InvalidArgumentException('O multiplicador da fornada precisa ser maior que zero.');
        }

        return DB::transaction(function () use ($receita, $multiplicador, $data, $observacoes) {
            $receita = Receita::query()->with('itens')->lockForUpdate()->findOrFail($receita->id);
            $produto = Produto::query()->lockForUpdate()->findOrFail($receita->produto_id);

            $custoTotal = '0';

            // 1. baixa cada insumo e acumula o custo real da fornada
            foreach ($receita->itens as $item) {
                $insumo = Insumo::query()->lockForUpdate()->findOrFail($item->insumo_id);

                $baixa = Decimal::quantidade(Decimal::mul($item->quantidade, $multiplicador));
                $custoLinha = Decimal::mul($baixa, $insumo->custo_medio);

                // estoque negativo é permitido de propósito: eles usam a farinha
                // antes de lançar a compra, e travar isso mata o uso do sistema
                $insumo->forceFill([
                    'estoque_atual' => Decimal::quantidade(
                        Decimal::sub($insumo->estoque_atual, $baixa)
                    ),
                ])->save();

                $custoTotal = Decimal::soma($custoTotal, $custoLinha);

                $movimentacoesInsumo[] = [
                    'insumo' => $insumo,
                    'quantidade' => $baixa,
                    'custo_unitario' => $insumo->custo_medio,
                ];
            }

            $custoTotal = Decimal::dinheiro($custoTotal);
            $quantidadeProduzida = Decimal::quantidade(
                Decimal::mul($receita->rendimento, $multiplicador)
            );

            // 2. registra a produção
            $producao = Producao::create([
                'receita_id' => $receita->id,
                'data' => $data ?? now()->toDateString(),
                'multiplicador' => $multiplicador,
                'quantidade_produzida' => $quantidadeProduzida,
                'custo_total' => $custoTotal,
                'observacoes' => $observacoes,
            ]);

            // 3. movimentações de saída dos insumos
            foreach ($movimentacoesInsumo ?? [] as $mov) {
                $mov['insumo']->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Saida,
                    'quantidade' => $mov['quantidade'],
                    'custo_unitario' => Decimal::custo($mov['custo_unitario']),
                    'observacao' => 'Produção de '.$produto->nome,
                    'origem_id' => $producao->id,
                    'origem_type' => Producao::class,
                ]);
            }

            // 4. entra o lote no produto, misturando com o estoque anterior
            $this->misturarLoteNoProduto($produto, $quantidadeProduzida, $custoTotal);

            $produto->movimentacoes()->create([
                'tipo' => TipoMovimentacao::Entrada,
                'quantidade' => $quantidadeProduzida,
                'custo_unitario' => Decimal::custo(
                    Decimal::div($custoTotal, $quantidadeProduzida)
                ),
                'observacao' => 'Fornada '.$multiplicador.'x de '.$receita->nome_exibicao,
                'origem_id' => $producao->id,
                'origem_type' => Producao::class,
            ]);

            return $producao;
        });
    }

    /**
     * Média ponderada entre o que já estava no estoque e o lote novo:
     *
     *   custo_unitario = (estoque_anterior * custo_anterior + custo_do_lote)
     *                    / (estoque_anterior + quantidade_do_lote)
     *
     * Se o estoque anterior estava negativo, ele é tratado como zero: o custo
     * do lote novo é o único dado confiável ali.
     */
    private function misturarLoteNoProduto(Produto $produto, string $quantidadeLote, string $custoLote): void
    {
        $estoqueAnterior = Decimal::positivo($produto->estoque_atual)
            ? (string) $produto->estoque_atual
            : '0';

        $valorAnterior = Decimal::mul($estoqueAnterior, $produto->custo_unitario);
        $novaQtd = Decimal::soma($estoqueAnterior, $quantidadeLote);

        $novoCusto = Decimal::positivo($novaQtd)
            ? Decimal::div(Decimal::soma($valorAnterior, $custoLote), $novaQtd)
            : '0';

        $produto->forceFill([
            // o estoque real segue o saldo verdadeiro, inclusive se estava negativo
            'estoque_atual' => Decimal::quantidade(
                Decimal::soma($produto->estoque_atual, $quantidadeLote)
            ),
            'custo_unitario' => Decimal::custo($novoCusto),
        ])->save();
    }

    /**
     * Insumos que ficariam negativos com esse multiplicador.
     * Só para avisar na tela — a produção acontece de qualquer jeito.
     *
     * @return array<int, array{nome: string, falta: string, unidade: string}>
     */
    public function insumosInsuficientes(Receita $receita, string $multiplicador = '1'): array
    {
        $faltando = [];

        foreach ($receita->itens as $item) {
            $necessario = Decimal::mul($item->quantidade, $multiplicador);
            $disponivel = (string) $item->insumo->estoque_atual;

            if (Decimal::menor($disponivel, $necessario)) {
                $faltando[] = [
                    'nome' => $item->insumo->nome,
                    'falta' => Decimal::quantidade(Decimal::sub($necessario, $disponivel)),
                    'unidade' => $item->insumo->unidade_base->sufixo(),
                ];
            }
        }

        return $faltando;
    }
}
