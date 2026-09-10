<?php

use App\Enums\CategoriaDespesa;
use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Models\Despesa;
use App\Models\Insumo;
use App\Models\Pedido;
use App\Models\Produto;
use App\Services\FinalizarPedido;
use App\Services\RelatorioFinanceiro;

function produtoCom(string $nome, string $custo, string $preco): Produto
{
    $produto = Produto::create(['nome' => $nome, 'preco_venda' => $preco]);
    $produto->forceFill(['custo_unitario' => $custo, 'estoque_atual' => '1000'])->save();

    return $produto->refresh();
}

function vender(Produto $produto, string $qtd, array $atributos = []): Pedido
{
    $pedido = Pedido::create(array_merge([
        'tipo' => TipoPedido::Avulsa,
        'status' => StatusPedido::Entregue,
        'data' => now()->toDateString(),
    ], $atributos));

    $pedido->itens()->create([
        'produto_id' => $produto->id,
        'quantidade' => $qtd,
        'preco_unitario' => $produto->preco_venda,
    ]);

    return app(FinalizarPedido::class)->efetivar($pedido->refresh());
}

beforeEach(function () {
    $this->relatorio = app(RelatorioFinanceiro::class);
    $this->de = now()->startOfMonth();
    $this->ate = now()->endOfMonth();
});

it('fecha o resultado do periodo', function () {
    $brownie = produtoCom('Brownie', '1.5000', '8.00');

    vender($brownie, '10', ['taxa_operadora' => '4.00', 'desconto' => '5.00']);
    vender($brownie, '5');

    Despesa::create([
        'categoria' => CategoriaDespesa::ProLabore,
        'descricao' => 'Pró-labore',
        'valor' => '40.00',
        'data' => now()->toDateString(),
    ]);

    $resumo = $this->relatorio->resumo($this->de, $this->ate);

    // bruto: 80 + 40 = 120 ; desconto 5 -> 115 ; taxa 4 -> 111 ; CMV 15*1,50 = 22,50
    expect($resumo['faturamento_bruto'])->toBe('115.00')
        ->and($resumo['taxas'])->toBe('4.00')
        ->and($resumo['faturamento_liquido'])->toBe('111.00')
        ->and($resumo['cmv'])->toBe('22.50')
        ->and($resumo['margem_contribuicao'])->toBe('88.50')
        ->and($resumo['despesas'])->toBe('40.00')
        ->and($resumo['lucro_liquido'])->toBe('48.50')
        ->and($resumo['unidades_vendidas'])->toBe('15.000')
        ->and($resumo['pedidos'])->toBe(2);
});

it('ignora orcamento e cancelado no faturamento', function () {
    $brownie = produtoCom('Brownie', '1.5000', '8.00');

    vender($brownie, '10');
    vender($brownie, '99', ['status' => StatusPedido::Orcamento]);
    vender($brownie, '99', ['status' => StatusPedido::Cancelado]);

    expect($this->relatorio->resumo($this->de, $this->ate)['faturamento_bruto'])->toBe('80.00');
});

it('ignora pedidos fora do periodo', function () {
    $brownie = produtoCom('Brownie', '1.5000', '8.00');

    vender($brownie, '10');
    vender($brownie, '10', ['data' => now()->subMonths(3)->toDateString()]);

    expect($this->relatorio->resumo($this->de, $this->ate)['faturamento_bruto'])->toBe('80.00');
});

it('ranqueia a margem por produto e rateia a taxa', function () {
    $brownie = produtoCom('Brownie', '1.5000', '8.00');
    $cookie = produtoCom('Cookie', '4.0000', '5.00');

    // brownie fatura 80, cookie 50 -> total 130; taxa 13,00 rateada 8/13 e 5/13
    vender($brownie, '10', ['taxa_operadora' => '8.00']);
    vender($cookie, '10', ['taxa_operadora' => '5.00']);

    $linhas = $this->relatorio->margemPorProduto($this->de, $this->ate);

    expect($linhas)->toHaveCount(2)
        // brownie sustenta o negócio: aparece primeiro
        ->and($linhas[0]['nome'])->toBe('Brownie')
        ->and($linhas[0]['receita'])->toBe('80.00')
        ->and($linhas[0]['custo'])->toBe('15.00')
        ->and($linhas[0]['taxa_rateada'])->toBe('8.00')
        ->and($linhas[0]['margem'])->toBe('57.00')
        ->and($linhas[1]['nome'])->toBe('Cookie')
        ->and($linhas[1]['margem'])->toBe('5.00');

    // o rateio devolve exatamente a taxa total, sem centavo sobrando
    $somaTaxas = bcadd($linhas[0]['taxa_rateada'], $linhas[1]['taxa_rateada'], 2);
    expect($somaTaxas)->toBe('13.00');
});

it('calcula o ponto de equilibrio em unidades inteiras', function () {
    $brownie = produtoCom('Brownie', '1.5000', '8.00');

    // 10 un: margem de contribuição 80 − 15 = 65 -> 6,50 por unidade
    vender($brownie, '10');

    Despesa::create([
        'categoria' => CategoriaDespesa::Aluguel,
        'descricao' => 'Aluguel',
        'valor' => '650.00',
        'data' => now()->toDateString(),
    ]);

    $equilibrio = $this->relatorio->pontoEquilibrio($this->de, $this->ate);

    expect($equilibrio['margem_unitaria_media'])->toBe('6.50')
        ->and($equilibrio['unidades'])->toBe('100');
});

it('arredonda o ponto de equilibrio para cima', function () {
    $brownie = produtoCom('Brownie', '1.5000', '8.00');
    vender($brownie, '10'); // 6,50 por unidade

    Despesa::create([
        'categoria' => CategoriaDespesa::Aluguel,
        'descricao' => 'Aluguel',
        'valor' => '655.00',
        'data' => now()->toDateString(),
    ]);

    // 655 / 6,50 = 100,77 -> meia unidade não paga conta
    expect($this->relatorio->pontoEquilibrio($this->de, $this->ate)['unidades'])->toBe('101');
});

it('agrupa despesas por categoria da maior para a menor', function () {
    foreach ([
        [CategoriaDespesa::Embalagem, '30.00'],
        [CategoriaDespesa::ProLabore, '500.00'],
        [CategoriaDespesa::Embalagem, '20.00'],
    ] as [$categoria, $valor]) {
        Despesa::create([
            'categoria' => $categoria,
            'descricao' => 'x',
            'valor' => $valor,
            'data' => now()->toDateString(),
        ]);
    }

    $linhas = $this->relatorio->despesasPorCategoria($this->de, $this->ate);

    expect($linhas)->toHaveCount(2)
        ->and($linhas[0]['categoria'])->toBe(CategoriaDespesa::ProLabore)
        ->and($linhas[0]['valor'])->toBe('500.00')
        ->and($linhas[1]['valor'])->toBe('50.00');
});

it('monta a serie diaria com todos os dias do intervalo', function () {
    $brownie = produtoCom('Brownie', '1.5000', '8.00');
    vender($brownie, '10', ['data' => now()->toDateString()]);

    $serie = $this->relatorio->faturamentoDiario(now()->subDays(6), now());

    expect($serie)->toHaveCount(7)
        ->and($serie[now()->toDateString()])->toBe('80.00')
        ->and($serie[now()->subDays(3)->toDateString()])->toBe('0.00');
});

it('soma o valor parado em estoque', function () {
    produtoCom('Brownie', '1.5000', '8.00'); // 1000 un x 1,50 = 1500

    Insumo::create([
        'nome' => 'Chocolate',
        'unidade_base' => 'g',
        'estoque_atual' => '5000',
        'custo_medio' => '0.0400',
        'estoque_minimo' => '0',
    ]); // 200,00

    $estoque = $this->relatorio->valorEmEstoque();

    expect($estoque['insumos'])->toBe('200.00')
        ->and($estoque['produtos'])->toBe('1500.00')
        ->and($estoque['total'])->toBe('1700.00');
});

it('devolve zeros num periodo sem movimento', function () {
    $resumo = $this->relatorio->resumo($this->de, $this->ate);

    expect($resumo['faturamento_bruto'])->toBe('0.00')
        ->and($resumo['lucro_liquido'])->toBe('0.00')
        ->and($resumo['ticket_medio'])->toBe('0.00')
        ->and($resumo['pedidos'])->toBe(0);
});
