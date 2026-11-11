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
 *
 * Parte da fornada pode levar recheio (ex.: 7 com Nutella) e sair como outro
 * produto. Cada unidade leva a mesma fatia da massa; a recheada soma o recheio.
 */
class ExecutarProducao
{
    /**
     * @param  string  $multiplicador  0.5 = meia fornada, 3 = três fornadas.
     * @param  array<int, string|null>  $recheios  [receita_recheio_id => unidades recheadas].
     *                                             O resto da fornada sai no produto da ficha.
     */
    public function executar(
        Receita $receita,
        string $multiplicador = '1',
        ?string $data = null,
        ?string $observacoes = null,
        array $recheios = [],
    ): Producao {
        $multiplicador = Decimal::quantidade($multiplicador);

        if (! Decimal::positivo($multiplicador)) {
            throw new InvalidArgumentException('O multiplicador da fornada precisa ser maior que zero.');
        }

        $recheios = $this->normalizarRecheios($recheios);

        return DB::transaction(function () use ($receita, $multiplicador, $data, $observacoes, $recheios) {
            $receita = Receita::query()->with('itens')->lockForUpdate()->findOrFail($receita->id);

            $quantidadeProduzida = Decimal::quantidade(
                Decimal::mul($receita->rendimento, $multiplicador)
            );

            $variacoes = $receita->recheios()->whereKey(array_keys($recheios))->get();

            if ($variacoes->count() !== count($recheios)) {
                throw new InvalidArgumentException('Recheio não pertence a esta ficha.');
            }

            $unidadesRecheadas = Decimal::somaLista($recheios);

            if (Decimal::maior($unidadesRecheadas, $quantidadeProduzida)) {
                throw new InvalidArgumentException(
                    'A fornada rende '.Decimal::paraBrEnxuto($quantidadeProduzida)
                    .' unidades; não dá para rechear '.Decimal::paraBrEnxuto($unidadesRecheadas).'.'
                );
            }

            $custoMassa = '0';
            $movimentacoesInsumo = [];

            // 1. baixa cada insumo da massa e acumula o custo real da fornada
            foreach ($receita->itens as $item) {
                $mov = $this->baixarInsumo(
                    $item->insumo_id,
                    Decimal::quantidade(Decimal::mul($item->quantidade, $multiplicador)),
                );
                $movimentacoesInsumo[] = $mov;
                $custoMassa = Decimal::soma($custoMassa, $mov['custo']);
            }

            $custoMassa = Decimal::dinheiro($custoMassa);
            $custoTotal = $custoMassa;
            $sobra = $quantidadeProduzida;
            $massaRestante = $custoMassa;
            $lotes = [];

            // 2. cada recheio leva sua fatia da massa + o próprio recheio
            foreach ($variacoes as $variacao) {
                $unidades = $recheios[$variacao->id];

                $mov = $this->baixarInsumo(
                    $variacao->insumo_id,
                    Decimal::quantidade(Decimal::mul($variacao->quantidade, $unidades)),
                );
                $movimentacoesInsumo[] = $mov;

                $parteMassa = Decimal::div(Decimal::mul($custoMassa, $unidades), $quantidadeProduzida);
                $massaRestante = Decimal::sub($massaRestante, $parteMassa);
                $sobra = Decimal::sub($sobra, $unidades);
                $custoTotal = Decimal::soma($custoTotal, $mov['custo']);

                $lotes[] = [$variacao->produto_id, $unidades, Decimal::soma($parteMassa, $mov['custo'])];
            }

            // a sobra leva o resto da massa: a soma dos lotes fecha com o custo da fornada
            if (Decimal::positivo($sobra)) {
                $lotes[] = [$receita->produto_id, Decimal::quantidade($sobra), $massaRestante];
            }

            // 3. registra a produção
            $producao = Producao::create([
                'receita_id' => $receita->id,
                'data' => $data ?? now()->toDateString(),
                'multiplicador' => $multiplicador,
                'quantidade_produzida' => $quantidadeProduzida,
                'custo_total' => Decimal::dinheiro($custoTotal),
                'observacoes' => $observacoes,
            ]);

            $nomeProduto = Produto::query()->whereKey($receita->produto_id)->value('nome');

            // 4. movimentações de saída dos insumos
            foreach ($movimentacoesInsumo as $mov) {
                $mov['insumo']->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Saida,
                    'quantidade' => $mov['quantidade'],
                    'custo_unitario' => Decimal::custo($mov['custo_unitario']),
                    'observacao' => 'Produção de '.$nomeProduto,
                    'origem_id' => $producao->id,
                    'origem_type' => Producao::class,
                ]);
            }

            // 5. entra cada lote no seu produto, misturando com o estoque anterior
            foreach ($lotes as [$produtoId, $quantidade, $custoLote]) {
                $produto = Produto::query()->lockForUpdate()->findOrFail($produtoId);

                $this->misturarLoteNoProduto($produto, $quantidade, $custoLote);

                $produto->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Entrada,
                    'quantidade' => $quantidade,
                    'custo_unitario' => Decimal::custo(Decimal::div($custoLote, $quantidade)),
                    'observacao' => 'Fornada '.$multiplicador.'x de '.$receita->nome_exibicao,
                    'origem_id' => $producao->id,
                    'origem_type' => Producao::class,
                ]);
            }

            return $producao;
        });
    }

    /**
     * Estoque negativo é permitido de propósito: eles usam a farinha antes de
     * lançar a compra, e travar isso mata o uso do sistema.
     *
     * @return array{insumo: Insumo, quantidade: string, custo_unitario: string, custo: string}
     */
    private function baixarInsumo(int $insumoId, string $baixa): array
    {
        $insumo = Insumo::query()->lockForUpdate()->findOrFail($insumoId);

        $insumo->forceFill([
            'estoque_atual' => Decimal::quantidade(Decimal::sub($insumo->estoque_atual, $baixa)),
        ])->save();

        return [
            'insumo' => $insumo,
            'quantidade' => $baixa,
            'custo_unitario' => (string) $insumo->custo_medio,
            'custo' => Decimal::mul($baixa, $insumo->custo_medio),
        ];
    }

    /**
     * Tira zeros e vazios, recusa negativos.
     *
     * @return array<int, string>
     */
    private function normalizarRecheios(array $recheios): array
    {
        $limpos = [];

        foreach ($recheios as $id => $unidades) {
            $unidades = Decimal::quantidade($unidades ?: '0');

            if (Decimal::negativo($unidades)) {
                throw new InvalidArgumentException('A quantidade recheada não pode ser negativa.');
            }

            if (Decimal::positivo($unidades)) {
                $limpos[(int) $id] = $unidades;
            }
        }

        return $limpos;
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
     * Desfaz uma produção lançada errada: devolve os insumos e retira as
     * unidades produzidas, a partir das movimentações que ela mesma gravou.
     * Nada é apagado — cada linha ganha o lançamento inverso. Idempotente.
     *
     * Fornada queimada não passa por aqui: aquilo é perda, e perda tem custo.
     */
    public function estornar(Producao $producao): Producao
    {
        return DB::transaction(function () use ($producao) {
            $producao = Producao::query()->lockForUpdate()->findOrFail($producao->id);

            if ($producao->foiEstornada()) {
                return $producao;
            }

            $originais = $producao->movimentacoes()
                ->whereIn('tipo', [TipoMovimentacao::Saida, TipoMovimentacao::Entrada])
                ->orderBy('id')
                ->get();

            foreach ($originais as $mov) {
                if ($mov->insumo_id) {
                    $insumo = Insumo::query()->lockForUpdate()->findOrFail($mov->insumo_id);

                    // shortcut: o custo médio do insumo não é refeito; se entrou compra
                    // com outro preço entre a fornada e o estorno, repesar aqui
                    $insumo->forceFill([
                        'estoque_atual' => Decimal::quantidade(Decimal::soma($insumo->estoque_atual, $mov->quantidade)),
                    ])->save();

                    $devolvido = $mov->quantidade;
                    $dono = $insumo;
                } else {
                    $produto = Produto::query()->lockForUpdate()->findOrFail($mov->produto_id);

                    $this->retirarLoteDoProduto(
                        $produto,
                        (string) $mov->quantidade,
                        Decimal::mul($mov->quantidade, $mov->custo_unitario),
                    );

                    $devolvido = Decimal::mul($mov->quantidade, '-1');
                    $dono = $produto;
                }

                $dono->movimentacoes()->create([
                    'tipo' => TipoMovimentacao::Ajuste,
                    'quantidade' => Decimal::quantidade($devolvido),
                    'custo_unitario' => Decimal::custo($mov->custo_unitario),
                    'observacao' => 'Estorno da produção de '.$producao->data->format('d/m/Y'),
                    'origem_id' => $producao->id,
                    'origem_type' => Producao::class,
                ]);
            }

            $producao->forceFill(['estornada_em' => now()])->save();

            return $producao;
        });
    }

    /**
     * O inverso de misturarLoteNoProduto: tira o lote da média.
     *
     *   custo_unitario = (estoque * custo_atual - custo_do_lote)
     *                    / (estoque - quantidade_do_lote)
     *
     * Se o lote já foi vendido e não sobra base positiva, o custo fica como
     * está: é o último número confiável.
     */
    private function retirarLoteDoProduto(Produto $produto, string $quantidadeLote, string $custoLote): void
    {
        $base = Decimal::positivo($produto->estoque_atual) ? (string) $produto->estoque_atual : '0';
        $novaBase = Decimal::sub($base, $quantidadeLote);
        $valor = Decimal::sub(Decimal::mul($base, $produto->custo_unitario), $custoLote);

        $novoCusto = Decimal::positivo($novaBase) && Decimal::positivo($valor)
            ? Decimal::div($valor, $novaBase)
            : (string) $produto->custo_unitario;

        $produto->forceFill([
            'estoque_atual' => Decimal::quantidade(Decimal::sub($produto->estoque_atual, $quantidadeLote)),
            'custo_unitario' => Decimal::custo($novoCusto),
        ])->save();
    }

    /**
     * Insumos que ficariam negativos com esse multiplicador e esses recheios.
     * Só para avisar na tela — a produção acontece de qualquer jeito.
     *
     * @param  array<int, string|null>  $recheios  [receita_recheio_id => unidades]
     * @return array<int, array{nome: string, falta: string, unidade: string}>
     */
    public function insumosInsuficientes(Receita $receita, string $multiplicador = '1', array $recheios = []): array
    {
        // soma por insumo: a Nutella pode estar na massa e no recheio
        $necessarios = [];

        foreach ($receita->itens as $item) {
            $necessarios[$item->insumo_id] = [$item->insumo, Decimal::soma(
                $necessarios[$item->insumo_id][1] ?? '0',
                Decimal::mul($item->quantidade, $multiplicador),
            )];
        }

        foreach ($receita->recheios as $recheio) {
            $unidades = $recheios[$recheio->id] ?? null;

            if (! $unidades || ! Decimal::positivo($unidades)) {
                continue;
            }

            $necessarios[$recheio->insumo_id] = [$recheio->insumo, Decimal::soma(
                $necessarios[$recheio->insumo_id][1] ?? '0',
                Decimal::mul($recheio->quantidade, $unidades),
            )];
        }

        $faltando = [];

        foreach ($necessarios as [$insumo, $necessario]) {
            $disponivel = (string) $insumo->estoque_atual;

            if (Decimal::menor($disponivel, $necessario)) {
                $faltando[] = [
                    'nome' => $insumo->nome,
                    'falta' => Decimal::quantidade(Decimal::sub($necessario, $disponivel)),
                    'unidade' => $insumo->unidade_base->sufixo(),
                ];
            }
        }

        return $faltando;
    }
}
