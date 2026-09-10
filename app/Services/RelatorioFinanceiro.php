<?php

namespace App\Services;

use App\Enums\CategoriaDespesa;
use App\Enums\StatusPedido;
use App\Models\Despesa;
use App\Models\Pedido;
use App\Models\Produto;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Números do fechamento.
 *
 * A agregação é feita em PHP com bcmath em vez de SUM() no banco. O volume é
 * pequeno (algumas centenas de pedidos por mês) e assim nenhum centavo passa
 * por float no caminho — que é exatamente o erro que o CLAUDE.md manda evitar.
 */
class RelatorioFinanceiro
{
    /**
     * @return array{
     *     faturamento_bruto: string, descontos: string, taxas: string,
     *     faturamento_liquido: string, cmv: string, margem_contribuicao: string,
     *     despesas: string, lucro_liquido: string, unidades_vendidas: string,
     *     pedidos: int, ticket_medio: string, margem_percentual: string
     * }
     */
    public function resumo(Carbon $de, Carbon $ate): array
    {
        $bruto = '0';       // soma dos itens, antes do desconto
        $descontos = '0';
        $taxas = '0';
        $cmv = '0';
        $unidades = '0';
        $pedidos = 0;

        $this->pedidosDoPeriodo($de, $ate)->chunk(200, function ($lote) use (
            &$bruto, &$descontos, &$taxas, &$cmv, &$unidades, &$pedidos
        ) {
            foreach ($lote as $pedido) {
                $pedidos++;
                $descontos = Decimal::soma($descontos, $pedido->desconto);
                $taxas = Decimal::soma($taxas, $pedido->taxa_operadora);

                foreach ($pedido->itens as $item) {
                    $bruto = Decimal::soma($bruto, $item->totalLinha());
                    $cmv = Decimal::soma($cmv, $item->custoLinha());
                    $unidades = Decimal::soma($unidades, $item->quantidade);
                }
            }
        });

        $faturamentoBruto = Decimal::sub($bruto, $descontos);
        $faturamentoLiquido = Decimal::sub($faturamentoBruto, $taxas);
        $margemContribuicao = Decimal::sub($faturamentoLiquido, $cmv);
        $despesas = $this->totalDespesas($de, $ate);
        $lucroLiquido = Decimal::sub($margemContribuicao, $despesas);

        return [
            'faturamento_bruto' => Decimal::dinheiro($faturamentoBruto),
            'descontos' => Decimal::dinheiro($descontos),
            'taxas' => Decimal::dinheiro($taxas),
            'faturamento_liquido' => Decimal::dinheiro($faturamentoLiquido),
            'cmv' => Decimal::dinheiro($cmv),
            'margem_contribuicao' => Decimal::dinheiro($margemContribuicao),
            'despesas' => Decimal::dinheiro($despesas),
            'lucro_liquido' => Decimal::dinheiro($lucroLiquido),
            'unidades_vendidas' => Decimal::quantidade($unidades),
            'pedidos' => $pedidos,
            'ticket_medio' => $pedidos > 0
                ? Decimal::dinheiro(Decimal::div($faturamentoBruto, (string) $pedidos))
                : '0.00',
            'margem_percentual' => Decimal::positivo($faturamentoLiquido)
                ? Decimal::arredonda(
                    Decimal::mul(Decimal::div($lucroLiquido, $faturamentoLiquido), '100'), 1
                )
                : '0.0',
        ];
    }

    /**
     * Margem de contribuição por produto: qual sabor realmente sustenta o negócio.
     * A taxa da operadora é rateada por participação no faturamento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function margemPorProduto(Carbon $de, Carbon $ate): array
    {
        $linhas = [];
        $totalReceita = '0';

        $this->pedidosDoPeriodo($de, $ate)->chunk(200, function ($lote) use (&$linhas, &$totalReceita) {
            foreach ($lote as $pedido) {
                foreach ($pedido->itens as $item) {
                    $id = $item->produto_id;

                    $linhas[$id] ??= [
                        'produto_id' => $id,
                        'nome' => $item->produto?->nome ?? 'Produto removido',
                        'quantidade' => '0',
                        'receita' => '0',
                        'custo' => '0',
                    ];

                    $linhas[$id]['quantidade'] = Decimal::soma($linhas[$id]['quantidade'], $item->quantidade);
                    $linhas[$id]['receita'] = Decimal::soma($linhas[$id]['receita'], $item->totalLinha());
                    $linhas[$id]['custo'] = Decimal::soma($linhas[$id]['custo'], $item->custoLinha());
                    $totalReceita = Decimal::soma($totalReceita, $item->totalLinha());
                }
            }
        });

        $taxas = $this->totalTaxas($de, $ate);

        foreach ($linhas as $id => $linha) {
            // rateio da taxa proporcional ao quanto o produto faturou
            $participacao = Decimal::positivo($totalReceita)
                ? Decimal::div($linha['receita'], $totalReceita)
                : '0';
            $taxaRateada = Decimal::mul($taxas, $participacao);

            $receitaLiquida = Decimal::sub($linha['receita'], $taxaRateada);
            $margem = Decimal::sub($receitaLiquida, $linha['custo']);

            $linhas[$id]['receita'] = Decimal::dinheiro($linha['receita']);
            $linhas[$id]['custo'] = Decimal::dinheiro($linha['custo']);
            $linhas[$id]['taxa_rateada'] = Decimal::dinheiro($taxaRateada);
            $linhas[$id]['margem'] = Decimal::dinheiro($margem);
            $linhas[$id]['margem_unitaria'] = Decimal::positivo($linha['quantidade'])
                ? Decimal::dinheiro(Decimal::div($margem, $linha['quantidade']))
                : '0.00';
            $linhas[$id]['margem_percentual'] = Decimal::positivo($linha['receita'])
                ? Decimal::arredonda(Decimal::mul(Decimal::div($margem, $linha['receita']), '100'), 1)
                : '0.0';
            $linhas[$id]['quantidade'] = Decimal::quantidade($linha['quantidade']);
        }

        // quem sustenta o negócio primeiro
        $linhas = array_values($linhas);
        usort($linhas, fn ($a, $b) => Decimal::compara($b['margem'], $a['margem']));

        return $linhas;
    }

    /**
     * Ponto de equilíbrio: quantas unidades por mês pagam a operação.
     *
     * unidades = despesas do período / margem de contribuição média por unidade
     *
     * @return array{despesas: string, margem_unitaria_media: string, unidades: string, faturamento: string}
     */
    public function pontoEquilibrio(Carbon $de, Carbon $ate): array
    {
        $resumo = $this->resumo($de, $ate);
        $unidades = $resumo['unidades_vendidas'];

        $margemUnitaria = Decimal::positivo($unidades)
            ? Decimal::div($resumo['margem_contribuicao'], $unidades)
            : '0';

        $unidadesNecessarias = Decimal::positivo($margemUnitaria)
            ? Decimal::div($resumo['despesas'], $margemUnitaria)
            : '0';

        // arredonda para cima: meia unidade não paga conta
        $unidadesInteiras = $this->tetoInteiro($unidadesNecessarias);

        // quanto isso representa em faturamento, ao preço médio por unidade do período
        $precoMedio = Decimal::positivo($unidades)
            ? Decimal::div($resumo['faturamento_bruto'], $unidades)
            : '0';

        return [
            'despesas' => $resumo['despesas'],
            'margem_unitaria_media' => Decimal::dinheiro($margemUnitaria),
            'unidades' => $unidadesInteiras,
            'faturamento' => Decimal::dinheiro(Decimal::mul($precoMedio, $unidadesInteiras)),
        ];
    }

    /**
     * Faturamento (já com desconto) por dia, para o gráfico do dashboard.
     *
     * @return array<string, string> ['2026-09-01' => '340.00', ...]
     */
    public function faturamentoDiario(Carbon $de, Carbon $ate): array
    {
        $serie = [];

        for ($dia = $de->copy()->startOfDay(); $dia->lte($ate); $dia->addDay()) {
            $serie[$dia->toDateString()] = '0.00';
        }

        $this->pedidosDoPeriodo($de, $ate)->chunk(200, function ($lote) use (&$serie) {
            foreach ($lote as $pedido) {
                $chave = $pedido->data->toDateString();

                if (! array_key_exists($chave, $serie)) {
                    continue;
                }

                $serie[$chave] = Decimal::dinheiro(
                    Decimal::soma($serie[$chave], $pedido->total())
                );
            }
        });

        return $serie;
    }

    /**
     * Despesas do período agrupadas por categoria, maior primeiro.
     *
     * @return array<int, array{categoria: CategoriaDespesa, valor: string}>
     */
    public function despesasPorCategoria(Carbon $de, Carbon $ate): array
    {
        $linhas = [];

        Despesa::query()
            // whereDate compara só a parte da data: o cast 'date' do Laravel grava
            // '2026-09-10 00:00:00', e um whereBetween cru descartaria o último dia
            ->whereDate('data', '>=', $de->toDateString())
            ->whereDate('data', '<=', $ate->toDateString())
            ->get(['categoria', 'valor'])
            ->each(function (Despesa $despesa) use (&$linhas) {
                $chave = $despesa->categoria->value;
                $linhas[$chave] ??= ['categoria' => $despesa->categoria, 'valor' => '0'];
                $linhas[$chave]['valor'] = Decimal::soma($linhas[$chave]['valor'], $despesa->valor);
            });

        foreach ($linhas as $chave => $linha) {
            $linhas[$chave]['valor'] = Decimal::dinheiro($linha['valor']);
        }

        $linhas = array_values($linhas);
        usort($linhas, fn ($a, $b) => Decimal::compara($b['valor'], $a['valor']));

        return $linhas;
    }

    /**
     * Valor total parado em estoque de insumo e de produto acabado.
     *
     * @return array{insumos: string, produtos: string, total: string}
     */
    public function valorEmEstoque(): array
    {
        $insumos = '0';
        $produtos = '0';

        DB::table('insumos')->select('estoque_atual', 'custo_medio')->orderBy('id')
            ->chunk(500, function ($lote) use (&$insumos) {
                foreach ($lote as $linha) {
                    $insumos = Decimal::soma($insumos, Decimal::mul($linha->estoque_atual, $linha->custo_medio));
                }
            });

        DB::table('produtos')->select('estoque_atual', 'custo_unitario')->orderBy('id')
            ->chunk(500, function ($lote) use (&$produtos) {
                foreach ($lote as $linha) {
                    $produtos = Decimal::soma($produtos, Decimal::mul($linha->estoque_atual, $linha->custo_unitario));
                }
            });

        return [
            'insumos' => Decimal::dinheiro($insumos),
            'produtos' => Decimal::dinheiro($produtos),
            'total' => Decimal::dinheiro(Decimal::soma($insumos, $produtos)),
        ];
    }

    /**
     * Produtos cujo preço de venda está perto demais do custo.
     *
     * @return array<int, Produto>
     */
    public function produtosComMargemRuim(string $minimoPercentual = '30'): array
    {
        return Produto::query()
            ->ativos()
            ->where('custo_unitario', '>', 0)
            ->get()
            ->filter(fn (Produto $p) => Decimal::menor($p->margemPercentual(), $minimoPercentual))
            ->values()
            ->all();
    }

    /** Teto inteiro sem passar por float. */
    private function tetoInteiro(string $valor): string
    {
        $truncado = bcadd($valor, '0', 0);

        return Decimal::maior($valor, $truncado)
            ? bcadd($truncado, '1', 0)
            : $truncado;
    }

    private function pedidosDoPeriodo(Carbon $de, Carbon $ate)
    {
        return Pedido::query()
            ->with(['itens.produto'])
            ->whereNotIn('status', [StatusPedido::Orcamento->value, StatusPedido::Cancelado->value])
            // whereDate compara só a parte da data: o cast 'date' do Laravel grava
            // '2026-09-10 00:00:00', e um whereBetween cru descartaria o último dia
            ->whereDate('data', '>=', $de->toDateString())
            ->whereDate('data', '<=', $ate->toDateString())
            ->orderBy('id');
    }

    private function totalDespesas(Carbon $de, Carbon $ate): string
    {
        $total = '0';

        Despesa::query()
            // whereDate compara só a parte da data: o cast 'date' do Laravel grava
            // '2026-09-10 00:00:00', e um whereBetween cru descartaria o último dia
            ->whereDate('data', '>=', $de->toDateString())
            ->whereDate('data', '<=', $ate->toDateString())
            ->orderBy('id')
            ->chunk(500, function ($lote) use (&$total) {
                foreach ($lote as $despesa) {
                    $total = Decimal::soma($total, $despesa->valor);
                }
            });

        return $total;
    }

    private function totalTaxas(Carbon $de, Carbon $ate): string
    {
        $total = '0';

        $this->pedidosDoPeriodo($de, $ate)->chunk(200, function ($lote) use (&$total) {
            foreach ($lote as $pedido) {
                $total = Decimal::soma($total, $pedido->taxa_operadora);
            }
        });

        return $total;
    }
}
