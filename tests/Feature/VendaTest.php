<?php

use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Produto;
use App\Services\FinalizarPedido;

function brownie(string $custo = '1.5000', string $preco = '8.00', string $estoque = '100'): Produto
{
    $produto = Produto::create(['nome' => 'Brownie '.fake()->unique()->word(), 'preco_venda' => $preco]);

    // custo e estoque nascem da produção; aqui forçamos o estado para isolar a venda
    $produto->forceFill(['custo_unitario' => $custo, 'estoque_atual' => $estoque])->save();

    return $produto->refresh();
}

function pedidoDe(Produto $produto, string $qtd = '10', array $atributos = []): Pedido
{
    $pedido = Pedido::create(array_merge([
        'tipo' => TipoPedido::Avulsa,
        'status' => StatusPedido::Orcamento,
        'data' => now()->toDateString(),
    ], $atributos));

    $pedido->itens()->create([
        'produto_id' => $produto->id,
        'quantidade' => $qtd,
        'preco_unitario' => $produto->preco_venda,
    ]);

    return $pedido->refresh();
}

it('congela o custo do produto no momento da venda', function () {
    $produto = brownie(custo: '1.5000');
    $pedido = pedidoDe($produto, '10');

    app(FinalizarPedido::class)->efetivar($pedido);

    expect((string) $pedido->itens()->sole()->custo_unitario)->toBe('1.5000');
});

it('nao muda a margem historica quando o custo do produto sobe depois', function () {
    $produto = brownie(custo: '1.5000', preco: '8.00');
    $pedido = pedidoDe($produto, '10');

    app(FinalizarPedido::class)->efetivar($pedido);
    $pedido->refresh()->load('itens');

    $cmvNaVenda = $pedido->cmv();
    $margemNaVenda = $pedido->margemContribuicao();

    // o chocolate sobe e o custo do produto dobra
    $produto->forceFill(['custo_unitario' => '3.0000'])->save();

    $pedido->refresh()->load('itens');

    expect($pedido->cmv())->toBe($cmvNaVenda)
        ->and($pedido->cmv())->toBe('15.00')
        ->and($pedido->margemContribuicao())->toBe($margemNaVenda)
        ->and($pedido->margemContribuicao())->toBe('65.00');
});

it('baixa o estoque do produto ao efetivar', function () {
    $produto = brownie(estoque: '100');
    $pedido = pedidoDe($produto, '10');

    app(FinalizarPedido::class)->efetivar($pedido);

    expect((string) $produto->refresh()->estoque_atual)->toBe('90.000');
});

it('nao baixa o estoque duas vezes', function () {
    $produto = brownie(estoque: '100');
    $pedido = pedidoDe($produto, '10');
    $servico = app(FinalizarPedido::class);

    $servico->efetivar($pedido);
    $servico->efetivar($pedido);
    $servico->efetivar($pedido);

    expect((string) $produto->refresh()->estoque_atual)->toBe('90.000');
});

it('devolve o produto ao estoque no cancelamento', function () {
    $produto = brownie(estoque: '100');
    $pedido = pedidoDe($produto, '10');
    $servico = app(FinalizarPedido::class);

    $servico->mudarStatus($pedido, StatusPedido::Confirmado);
    expect((string) $produto->refresh()->estoque_atual)->toBe('90.000');

    $servico->mudarStatus($pedido->refresh(), StatusPedido::Cancelado);
    expect((string) $produto->refresh()->estoque_atual)->toBe('100.000');
});

it('orcamento nao mexe em estoque nem congela custo', function () {
    $produto = brownie(estoque: '100');
    $pedido = pedidoDe($produto, '10');

    app(FinalizarPedido::class)->sincronizar($pedido);

    expect((string) $produto->refresh()->estoque_atual)->toBe('100.000')
        ->and((string) $pedido->itens()->sole()->custo_unitario)->toBe('0.0000')
        ->and($pedido->refresh()->jaBaixouEstoque())->toBeFalse();
});

it('venda avulsa entregue ja efetiva na hora', function () {
    $produto = brownie(estoque: '100');
    $pedido = pedidoDe($produto, '10', ['status' => StatusPedido::Entregue]);

    app(FinalizarPedido::class)->sincronizar($pedido);

    expect((string) $produto->refresh()->estoque_atual)->toBe('90.000')
        ->and((string) $pedido->itens()->sole()->custo_unitario)->toBe('1.5000');
});

it('desconta a taxa da operadora do faturamento liquido', function () {
    $produto = brownie(custo: '1.5000', preco: '8.00');
    $pedido = pedidoDe($produto, '10', [
        'taxa_operadora' => '3.99',
        'desconto' => '5.00',
    ]);

    app(FinalizarPedido::class)->efetivar($pedido);
    $pedido->refresh()->load('itens');

    // 10 x 8,00 = 80,00  −5,00 desconto = 75,00  −3,99 taxa = 71,01  −15,00 CMV = 56,01
    expect($pedido->subtotal())->toBe('80.00')
        ->and($pedido->total())->toBe('75.00')
        ->and($pedido->faturamentoLiquido())->toBe('71.01')
        ->and($pedido->cmv())->toBe('15.00')
        ->and($pedido->margemContribuicao())->toBe('56.01');
});

it('calcula o saldo a receber descontando o sinal', function () {
    $produto = brownie(preco: '8.00');
    $cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '11987654321']);

    $pedido = pedidoDe($produto, '10', [
        'tipo' => TipoPedido::Encomenda,
        'cliente_id' => $cliente->id,
        'sinal_pago' => '30.00',
        'data_entrega' => now()->addDays(3)->toDateString(),
    ]);

    expect($pedido->saldoAReceber())->toBe('50.00');
});

it('percorre o fluxo de status ate a entrega', function () {
    $produto = brownie(estoque: '100');
    $pedido = pedidoDe($produto, '10', ['tipo' => TipoPedido::Encomenda]);
    $servico = app(FinalizarPedido::class);

    foreach ([StatusPedido::Confirmado, StatusPedido::EmProducao, StatusPedido::Pronto, StatusPedido::Entregue] as $status) {
        $pedido = $servico->mudarStatus($pedido, $status);
        expect($pedido->status)->toBe($status);
    }

    // o estoque saiu uma vez só, na confirmação
    expect((string) $produto->refresh()->estoque_atual)->toBe('90.000')
        ->and($produto->movimentacoes()->count())->toBe(1);
});

it('nao conta orcamento nem cancelado no faturamento', function () {
    expect(StatusPedido::Orcamento->contabiliza())->toBeFalse()
        ->and(StatusPedido::Cancelado->contabiliza())->toBeFalse()
        ->and(StatusPedido::Confirmado->contabiliza())->toBeTrue()
        ->and(StatusPedido::Entregue->contabiliza())->toBeTrue();
});
