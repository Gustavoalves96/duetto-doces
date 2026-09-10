<?php

use App\Models\Compra;
use App\Models\Insumo;
use App\Services\RegistrarCompra;

function insumo(array $atributos = []): Insumo
{
    return Insumo::create(array_merge([
        'nome' => 'Chocolate '.fake()->unique()->word(),
        'unidade_base' => 'g',
        'estoque_atual' => '0',
        'custo_medio' => '0',
        'estoque_minimo' => '0',
    ], $atributos));
}

function compraDe(Insumo $insumo, string $quantidade, string $valor): Compra
{
    $compra = Compra::create([
        'fornecedor' => 'Atacadão',
        'data' => now()->toDateString(),
    ]);

    $compra->itens()->create([
        'insumo_id' => $insumo->id,
        'quantidade' => $quantidade,
        'valor_total' => $valor,
    ]);

    return $compra;
}

it('grava o custo da primeira compra como custo médio', function () {
    $chocolate = insumo();

    app(RegistrarCompra::class)->aplicar(compraDe($chocolate, '1000', '34.80'));

    $chocolate->refresh();

    expect((string) $chocolate->estoque_atual)->toBe('1000.000')
        ->and((string) $chocolate->custo_medio)->toBe('0.0348');
});

it('pondera o custo entre o estoque velho e a compra nova', function () {
    $chocolate = insumo();
    $servico = app(RegistrarCompra::class);

    // 1000 g a R$ 34,80  -> 0,0348 por grama
    $servico->aplicar(compraDe($chocolate, '1000', '34.80'));
    // 1000 g a R$ 44,00  -> preço subiu
    $servico->aplicar(compraDe($chocolate, '1000', '44.00'));

    $chocolate->refresh();

    // média: (34,80 + 44,00) / 2000 = 0,0394
    expect((string) $chocolate->estoque_atual)->toBe('2000.000')
        ->and((string) $chocolate->custo_medio)->toBe('0.0394');
});

it('nao usa o ultimo preco pago', function () {
    $chocolate = insumo();
    $servico = app(RegistrarCompra::class);

    // muito estoque barato, depois uma comprinha cara
    $servico->aplicar(compraDe($chocolate, '10000', '300.00')); // 0,03
    $servico->aplicar(compraDe($chocolate, '100', '9.00'));     // 0,09

    $chocolate->refresh();

    // último preço seria 0,09; a média fica bem abaixo disso
    expect((string) $chocolate->custo_medio)->toBe('0.0306')
        ->and((string) $chocolate->custo_medio)->not->toBe('0.0900');
});

it('registra a movimentacao de entrada com o custo da nota', function () {
    $chocolate = insumo();

    app(RegistrarCompra::class)->aplicar(compraDe($chocolate, '2000', '80.00'));

    $movimentacao = $chocolate->movimentacoes()->sole();

    expect($movimentacao->tipo->value)->toBe('entrada')
        ->and((string) $movimentacao->quantidade)->toBe('2000.000')
        ->and((string) $movimentacao->custo_unitario)->toBe('0.0400');
});

it('soma o total da nota a partir dos itens', function () {
    $farinha = insumo(['nome' => 'Farinha']);
    $manteiga = insumo(['nome' => 'Manteiga']);

    $compra = Compra::create(['fornecedor' => 'Atacadão', 'data' => now()->toDateString()]);
    $compra->itens()->create(['insumo_id' => $farinha->id, 'quantidade' => '5000', 'valor_total' => '22.50']);
    $compra->itens()->create(['insumo_id' => $manteiga->id, 'quantidade' => '1000', 'valor_total' => '38.90']);

    $compra = app(RegistrarCompra::class)->aplicar($compra);

    expect((string) $compra->total)->toBe('61.40');
});

it('nao aplica a mesma compra duas vezes', function () {
    $chocolate = insumo();
    $compra = compraDe($chocolate, '1000', '34.80');
    $servico = app(RegistrarCompra::class);

    $servico->aplicar($compra);
    $servico->aplicar($compra);
    $servico->aplicar($compra);

    $chocolate->refresh();

    expect((string) $chocolate->estoque_atual)->toBe('1000.000')
        ->and($chocolate->movimentacoes()->count())->toBe(1);
});

it('desfaz a compra no estorno', function () {
    $chocolate = insumo();
    $servico = app(RegistrarCompra::class);

    $servico->aplicar(compraDe($chocolate, '1000', '34.80'));
    $segunda = compraDe($chocolate, '1000', '44.00');
    $servico->aplicar($segunda);

    $servico->estornar($segunda);

    $chocolate->refresh();

    // volta exatamente ao estado de antes da segunda nota
    expect((string) $chocolate->estoque_atual)->toBe('1000.000')
        ->and((string) $chocolate->custo_medio)->toBe('0.0348');
});

it('zera o custo medio quando o estoque fica zerado', function () {
    $chocolate = insumo();
    $compra = compraDe($chocolate, '1000', '34.80');
    $servico = app(RegistrarCompra::class);

    $servico->aplicar($compra);
    $servico->estornar($compra);

    $chocolate->refresh();

    expect((string) $chocolate->estoque_atual)->toBe('0.000')
        ->and((string) $chocolate->custo_medio)->toBe('0.0000');
});

it('nao acumula erro de centavo em muitas compras seguidas', function () {
    $chocolate = insumo();
    $servico = app(RegistrarCompra::class);

    // o caso classico do float: 0,1 somado muitas vezes
    for ($i = 0; $i < 100; $i++) {
        $servico->aplicar(compraDe($chocolate, '10', '0.10'));
    }

    $chocolate->refresh();

    expect((string) $chocolate->estoque_atual)->toBe('1000.000')
        ->and((string) $chocolate->custo_medio)->toBe('0.0100')
        ->and((string) $chocolate->valorEmEstoque())->toBe('10.00');
});
