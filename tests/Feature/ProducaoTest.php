<?php

use App\Models\Insumo;
use App\Models\Produto;
use App\Models\Receita;
use App\Services\ExecutarProducao;

/**
 * Cenário base: brownie que rende 20 unidades por fornada.
 * 500 g de chocolate a R$ 0,04/g = R$ 20,00
 * 200 g de manteiga  a R$ 0,05/g = R$ 10,00
 * total R$ 30,00 -> R$ 1,50 por brownie
 */
function cenarioBrownie(): array
{
    $chocolate = Insumo::create([
        'nome' => 'Chocolate', 'unidade_base' => 'g',
        'estoque_atual' => '5000', 'custo_medio' => '0.0400', 'estoque_minimo' => '1000',
    ]);

    $manteiga = Insumo::create([
        'nome' => 'Manteiga', 'unidade_base' => 'g',
        'estoque_atual' => '2000', 'custo_medio' => '0.0500', 'estoque_minimo' => '500',
    ]);

    $produto = Produto::create(['nome' => 'Brownie', 'preco_venda' => '8.00']);

    $receita = Receita::create([
        'produto_id' => $produto->id,
        'nome' => 'Brownie tradicional',
        'rendimento' => '20',
    ]);

    $receita->itens()->create(['insumo_id' => $chocolate->id, 'quantidade' => '500']);
    $receita->itens()->create(['insumo_id' => $manteiga->id, 'quantidade' => '200']);

    return compact('chocolate', 'manteiga', 'produto', 'receita');
}

it('calcula o custo do produto a partir da ficha tecnica', function () {
    ['produto' => $produto, 'receita' => $receita] = cenarioBrownie();

    $producao = app(ExecutarProducao::class)->executar($receita, '1');

    expect((string) $producao->custo_total)->toBe('30.00')
        ->and((string) $producao->quantidade_produzida)->toBe('20.000');

    $produto->refresh();

    expect((string) $produto->custo_unitario)->toBe('1.5000')
        ->and((string) $produto->estoque_atual)->toBe('20.000');
});

it('baixa os insumos na proporcao da receita', function () {
    ['chocolate' => $chocolate, 'manteiga' => $manteiga, 'receita' => $receita] = cenarioBrownie();

    app(ExecutarProducao::class)->executar($receita, '1');

    expect((string) $chocolate->refresh()->estoque_atual)->toBe('4500.000')
        ->and((string) $manteiga->refresh()->estoque_atual)->toBe('1800.000');
});

it('respeita o multiplicador de meia fornada', function () {
    ['chocolate' => $chocolate, 'produto' => $produto, 'receita' => $receita] = cenarioBrownie();

    $producao = app(ExecutarProducao::class)->executar($receita, '0.5');

    expect((string) $producao->quantidade_produzida)->toBe('10.000')
        ->and((string) $producao->custo_total)->toBe('15.00')
        ->and((string) $chocolate->refresh()->estoque_atual)->toBe('4750.000')
        // metade do insumo para metade das unidades: custo unitário não muda
        ->and((string) $produto->refresh()->custo_unitario)->toBe('1.5000');
});

it('respeita o multiplicador de tres fornadas', function () {
    ['chocolate' => $chocolate, 'produto' => $produto, 'receita' => $receita] = cenarioBrownie();

    $producao = app(ExecutarProducao::class)->executar($receita, '3');

    expect((string) $producao->quantidade_produzida)->toBe('60.000')
        ->and((string) $producao->custo_total)->toBe('90.00')
        ->and((string) $chocolate->refresh()->estoque_atual)->toBe('3500.000')
        ->and((string) $produto->refresh()->custo_unitario)->toBe('1.5000');
});

it('mistura o lote novo com o estoque anterior por media ponderada', function () {
    ['chocolate' => $chocolate, 'produto' => $produto, 'receita' => $receita] = cenarioBrownie();
    $servico = app(ExecutarProducao::class);

    // primeira fornada: 20 un a R$ 1,50
    $servico->executar($receita, '1');

    // chocolate encarece de 0,04 para 0,08
    $chocolate->forceFill(['custo_medio' => '0.0800'])->save();

    // segunda fornada: 500*0,08 + 200*0,05 = 50,00 -> 20 un a R$ 2,50
    $servico->executar($receita, '1');

    $produto->refresh();

    // (20*1,50 + 50,00) / 40 = 80,00 / 40 = 2,00
    expect((string) $produto->estoque_atual)->toBe('40.000')
        ->and((string) $produto->custo_unitario)->toBe('2.0000');
});

it('permite produzir sem estoque e deixa o insumo negativo', function () {
    ['chocolate' => $chocolate, 'manteiga' => $manteiga, 'produto' => $produto, 'receita' => $receita] = cenarioBrownie();

    // 5000 g de chocolate dá para 10 fornadas; pedimos 11
    $producao = app(ExecutarProducao::class)->executar($receita, '11');

    expect($producao->exists)->toBeTrue()
        ->and((string) $chocolate->refresh()->estoque_atual)->toBe('-500.000')
        ->and((string) $manteiga->refresh()->estoque_atual)->toBe('-200.000')
        ->and((string) $produto->refresh()->estoque_atual)->toBe('220.000');
});

it('avisa quais insumos vao faltar antes de produzir', function () {
    ['receita' => $receita] = cenarioBrownie();

    $faltando = app(ExecutarProducao::class)->insumosInsuficientes($receita, '11');

    expect($faltando)->toHaveCount(2)
        ->and($faltando[0]['nome'])->toBe('Chocolate')
        ->and($faltando[0]['falta'])->toBe('500.000')
        ->and($faltando[0]['unidade'])->toBe('g');
});

it('nao avisa nada quando o estoque cobre a fornada', function () {
    ['receita' => $receita] = cenarioBrownie();

    expect(app(ExecutarProducao::class)->insumosInsuficientes($receita, '1'))->toBeEmpty();
});

it('recusa multiplicador zero ou negativo', function () {
    ['receita' => $receita] = cenarioBrownie();

    expect(fn () => app(ExecutarProducao::class)->executar($receita, '0'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(ExecutarProducao::class)->executar($receita, '-2'))
        ->toThrow(InvalidArgumentException::class);
});

it('registra movimentacao de saida por insumo e uma de entrada do produto', function () {
    ['chocolate' => $chocolate, 'produto' => $produto, 'receita' => $receita] = cenarioBrownie();

    $producao = app(ExecutarProducao::class)->executar($receita, '2');

    $saidaChocolate = $chocolate->movimentacoes()->sole();
    $entradaProduto = $produto->movimentacoes()->sole();

    expect($saidaChocolate->tipo->value)->toBe('saida')
        ->and((string) $saidaChocolate->quantidade)->toBe('1000.000')
        ->and((string) $saidaChocolate->custo_unitario)->toBe('0.0400')
        ->and($entradaProduto->tipo->value)->toBe('entrada')
        ->and((string) $entradaProduto->quantidade)->toBe('40.000')
        ->and((string) $entradaProduto->custo_unitario)->toBe('1.5000')
        ->and($entradaProduto->origem_id)->toBe($producao->id);
});

it('nao deixa o custo do produto ser digitado a mao', function () {
    ['produto' => $produto, 'receita' => $receita] = cenarioBrownie();

    app(ExecutarProducao::class)->executar($receita, '1');
    $produto->refresh();

    expect((string) $produto->custo_unitario)->toBe('1.5000');

    // custo_unitario esta fora do fillable: preenchimento em massa nao encosta nele
    $produto->fill(['custo_unitario' => '999.9999', 'preco_venda' => '9.00']);

    expect((string) $produto->custo_unitario)->toBe('1.5000')
        ->and((string) $produto->preco_venda)->toBe('9.00');

    // e um create tambem ignora
    $outro = Produto::create(['nome' => 'Cookie', 'preco_venda' => '5.00', 'custo_unitario' => '42.0000']);

    expect((string) $outro->refresh()->custo_unitario)->toBe('0.0000');
});
