<?php

use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Filament\Resources\Pedidos\Pages\CreatePedido;
use App\Filament\Resources\Pedidos\Pages\EditPedido;
use App\Filament\Resources\Pedidos\Pages\ListPedidos;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use App\Services\FinalizarPedido;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Encomenda tem só dois estados na prática: a entregar e entregue.
 * Nada de confirmar, iniciar produção e marcar pronto no meio do caminho.
 */

beforeEach(fn () => actingAs(User::factory()->create()));

function produtoParaEncomenda(): Produto
{
    $produto = Produto::create(['nome' => 'Brownie tradicional', 'preco_venda' => '8.00']);
    $produto->forceFill(['custo_unitario' => '2.5000', 'estoque_atual' => '50'])->save();

    return $produto->refresh();
}

function encomendaAEntregar(Produto $produto, array $atributos = []): Pedido
{
    $pedido = Pedido::create(array_merge([
        'cliente_id' => Cliente::create(['nome' => 'Marina'])->id,
        'tipo' => TipoPedido::Encomenda,
        'status' => StatusPedido::Confirmado,
        'data' => now()->toDateString(),
        'data_entrega' => now()->addDays(3)->toDateString(),
    ], $atributos));

    $pedido->itens()->create([
        'produto_id' => $produto->id,
        'quantidade' => '4',
        'preco_unitario' => '8.00',
    ]);

    return app(FinalizarPedido::class)->sincronizar($pedido);
}

it('cria a encomenda já a entregar, com custo congelado e estoque baixado', function () {
    $produto = produtoParaEncomenda();
    $cliente = Cliente::create(['nome' => 'Marina']);

    Livewire::test(CreatePedido::class)
        ->fillForm(['tipo' => TipoPedido::Encomenda->value])
        ->fillForm([
            'cliente_id' => $cliente->id,
            'data' => now()->toDateString(),
            'data_entrega' => now()->addDays(2)->toDateString(),
            'itens' => [['produto_id' => $produto->id, 'quantidade' => '3', 'preco_unitario' => '8,00']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pedido = Pedido::sole();

    expect($pedido->status)->toBe(StatusPedido::Confirmado)
        ->and($pedido->jaBaixouEstoque())->toBeTrue()
        ->and((string) $pedido->itens()->sole()->quantidade)->toBe('3.000')
        ->and((string) $pedido->itens()->sole()->custo_unitario)->toBe('2.5000')
        ->and((string) $produto->refresh()->estoque_atual)->toBe('47.000');
});

it('mostra a quantidade do pedido sem casas decimais', function () {
    $pedido = encomendaAEntregar(produtoParaEncomenda());

    $itens = Livewire::test(EditPedido::class, ['record' => $pedido->getRouteKey()])
        ->instance()->form->getRawState()['itens'];

    expect(collect($itens)->first()['quantidade'])->toBe('4');
});

it('usa máscara de número inteiro na quantidade do pedido', function () {
    $html = Livewire::test(CreatePedido::class)->html();

    expect($html)->toContain(trim(<<<'HTML'
        x-mask:dynamic="$money($input, ',', '.', 0)"
        HTML));
});

it('recusa quantidade fracionada ou zero no pedido', function (string $quantidade, string $mensagem) {
    $produto = produtoParaEncomenda();

    Livewire::test(CreatePedido::class)
        ->fillForm([
            'tipo' => TipoPedido::Avulsa->value,
            'data' => now()->toDateString(),
            'itens' => [['produto_id' => $produto->id, 'quantidade' => $quantidade, 'preco_unitario' => '8,00']],
        ])
        ->call('create')
        ->assertSee($mensagem);

    expect(Pedido::count())->toBe(0);
})->with([
    'meio brownie' => ['1,5', 'Informe uma quantidade inteira'],
    'zero' => ['0', 'no mínimo 1'],
]);

it('marca a encomenda como entregue direto da listagem', function () {
    $pedido = encomendaAEntregar(produtoParaEncomenda());

    Livewire::test(ListPedidos::class)
        ->assertActionVisible(TestAction::make('entregar')->table($pedido))
        ->callAction(TestAction::make('entregar')->table($pedido));

    expect($pedido->refresh()->status)->toBe(StatusPedido::Entregue)
        ->and($pedido->jaBaixouEstoque())->toBeTrue();
});

it('pede confirmação antes de marcar como entregue', function () {
    $pedido = encomendaAEntregar(produtoParaEncomenda());

    Livewire::test(ListPedidos::class)
        ->mountAction(TestAction::make('entregar')->table($pedido))
        ->assertActionMounted(TestAction::make('entregar')->table($pedido));

    expect($pedido->refresh()->status)->toBe(StatusPedido::Confirmado);
});

it('dá nome ao botão que vira só ícone em telas menores', function () {
    encomendaAEntregar(produtoParaEncomenda());

    Livewire::test(ListPedidos::class)
        ->loadTable()
        ->assertSeeHtml('aria-label="Entregar"');
});

it('volta uma encomenda entregue para a entregar sem mexer no estoque', function () {
    $produto = produtoParaEncomenda();
    $pedido = encomendaAEntregar($produto, ['status' => StatusPedido::Entregue]);
    $estoque = (string) $produto->refresh()->estoque_atual;

    Livewire::test(ListPedidos::class)
        ->assertActionHidden(TestAction::make('entregar')->table($pedido))
        ->callAction(TestAction::make('desfazerEntrega')->table($pedido));

    expect($pedido->refresh()->status)->toBe(StatusPedido::Confirmado)
        ->and((string) $produto->refresh()->estoque_atual)->toBe($estoque);
});

it('altera a data de entrega sem mexer em status, estoque ou custo', function () {
    $produto = produtoParaEncomenda();
    $pedido = encomendaAEntregar($produto);
    $estoque = (string) $produto->refresh()->estoque_atual;
    $novaData = now()->addDays(10)->toDateString();

    Livewire::test(ListPedidos::class)
        ->callAction(TestAction::make('alterarEntrega')->table($pedido), ['data_entrega' => $novaData])
        ->assertHasNoFormErrors();

    $pedido->refresh();

    expect($pedido->data_entrega->toDateString())->toBe($novaData)
        ->and($pedido->status)->toBe(StatusPedido::Confirmado)
        ->and((string) $pedido->itens()->sole()->custo_unitario)->toBe('2.5000')
        ->and((string) $produto->refresh()->estoque_atual)->toBe($estoque);
});

it('não deixa a entrega ficar antes da data do pedido', function () {
    $pedido = encomendaAEntregar(produtoParaEncomenda());

    Livewire::test(ListPedidos::class)
        ->callAction(TestAction::make('alterarEntrega')->table($pedido), [
            'data_entrega' => now()->subDays(5)->toDateString(),
        ])
        ->assertHasFormErrors(['data_entrega']);

    expect($pedido->refresh()->data_entrega->toDateString())->toBe(now()->addDays(3)->toDateString());
});

it('não oferece ações de entrega para venda avulsa', function () {
    $produto = produtoParaEncomenda();
    $avulsa = Pedido::create([
        'tipo' => TipoPedido::Avulsa,
        'status' => StatusPedido::Entregue,
        'data' => now()->toDateString(),
    ]);
    $avulsa->itens()->create(['produto_id' => $produto->id, 'quantidade' => '1', 'preco_unitario' => '8.00']);

    Livewire::test(ListPedidos::class)
        ->assertActionHidden(TestAction::make('entregar')->table($avulsa))
        ->assertActionHidden(TestAction::make('desfazerEntrega')->table($avulsa))
        ->assertActionHidden(TestAction::make('alterarEntrega')->table($avulsa));
});

it('cria a encomenda já marcada como paga', function () {
    $produto = produtoParaEncomenda();
    $cliente = Cliente::create(['nome' => 'Marina']);

    Livewire::test(CreatePedido::class)
        ->fillForm(['tipo' => TipoPedido::Encomenda->value])
        ->fillForm([
            'cliente_id' => $cliente->id,
            'data' => now()->toDateString(),
            'data_entrega' => now()->addDays(2)->toDateString(),
            'pago' => true,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => '2', 'preco_unitario' => '8,00']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pedido = Pedido::sole();

    expect($pedido->pago)->toBeTrue()
        ->and($pedido->saldoAReceber())->toBe('0.00');
});

it('encomenda nova nasce não paga, com o saldo em aberto', function () {
    $pedido = encomendaAEntregar(produtoParaEncomenda(), ['sinal_pago' => '10.00']);

    expect($pedido->pago)->toBeFalse()
        ->and($pedido->saldoAReceber())->toBe('22.00');
});

it('marca e desmarca o pagamento pela listagem sem mexer em status nem estoque', function () {
    $produto = produtoParaEncomenda();
    $pedido = encomendaAEntregar($produto, ['sinal_pago' => '10.00']);
    $estoque = (string) $produto->refresh()->estoque_atual;

    Livewire::test(ListPedidos::class)
        ->callAction(TestAction::make('alternarPagamento')->table($pedido));

    $pedido->refresh();
    expect($pedido->pago)->toBeTrue()
        ->and($pedido->status)->toBe(StatusPedido::Confirmado)
        ->and((string) $pedido->sinal_pago)->toBe('10.00')
        ->and((string) $produto->refresh()->estoque_atual)->toBe($estoque);

    Livewire::test(ListPedidos::class)
        ->callAction(TestAction::make('alternarPagamento')->table($pedido));

    expect($pedido->refresh()->pago)->toBeFalse()
        ->and($pedido->saldoAReceber())->toBe('22.00');
});

it('filtra as encomendas não pagas', function () {
    $produto = produtoParaEncomenda();
    $naoPaga = encomendaAEntregar($produto);
    $paga = encomendaAEntregar($produto, ['pago' => true]);

    Livewire::test(ListPedidos::class)
        ->filterTable('nao_pagas')
        ->assertCanSeeTableRecords([$naoPaga])
        ->assertCanNotSeeTableRecords([$paga]);
});

it('trata venda avulsa como paga no balcão', function () {
    $produto = produtoParaEncomenda();
    $avulsa = Pedido::create([
        'tipo' => TipoPedido::Avulsa,
        'status' => StatusPedido::Entregue,
        'data' => now()->toDateString(),
    ]);
    $avulsa->itens()->create(['produto_id' => $produto->id, 'quantidade' => '1', 'preco_unitario' => '8.00']);

    expect($avulsa->refresh()->estaPago())->toBeTrue()
        ->and($avulsa->saldoAReceber())->toBe('0.00');

    Livewire::test(ListPedidos::class)
        ->assertActionHidden(TestAction::make('alternarPagamento')->table($avulsa));
});

/*
 * Editar um pedido que já baixou estoque: o que volta para a prateleira é o
 * que estava no pedido ANTES do save, e o que sai é o que ficou depois.
 */

function outroProduto(string $nome, string $custo = '4.0000'): Produto
{
    $produto = Produto::create(['nome' => $nome, 'preco_venda' => '9.00']);
    $produto->forceFill(['custo_unitario' => $custo, 'estoque_atual' => '50'])->save();

    return $produto->refresh();
}

it('devolve a diferença ao diminuir a quantidade de um pedido já baixado', function () {
    $produto = produtoParaEncomenda();
    $pedido = encomendaAEntregar($produto); // 4 unidades: estoque 50 -> 46

    $tela = Livewire::test(EditPedido::class, ['record' => $pedido->getRouteKey()]);
    $chave = array_key_first($tela->instance()->form->getRawState()['itens']);

    $tela->set("data.itens.{$chave}.quantidade", '2')
        ->call('save')
        ->assertHasNoFormErrors();

    expect((string) $pedido->itens()->sole()->quantidade)->toBe('2.000')
        ->and((string) $produto->refresh()->estoque_atual)->toBe('48.000');
});

it('devolve o item removido e baixa o item novo', function () {
    $brownie = produtoParaEncomenda();
    $cookie = outroProduto('Cookie');
    $palha = outroProduto('Palha italiana');

    // o cookie entra com o pedido ainda sem baixa, para as duas baixas saírem juntas
    $servico = app(FinalizarPedido::class);
    $pedido = $servico->estornar(encomendaAEntregar($brownie));
    $pedido->itens()->create(['produto_id' => $cookie->id, 'quantidade' => '3', 'preco_unitario' => '9.00']);
    $servico->sincronizar($pedido);

    expect((string) $brownie->refresh()->estoque_atual)->toBe('46.000')
        ->and((string) $cookie->refresh()->estoque_atual)->toBe('47.000');

    $tela = Livewire::test(EditPedido::class, ['record' => $pedido->getRouteKey()]);
    $itens = $tela->instance()->form->getRawState()['itens'];

    // sai o cookie, entra a palha
    $itens = array_filter($itens, fn (array $item) => (int) $item['produto_id'] !== $cookie->id);
    $itens['novo'] = ['produto_id' => $palha->id, 'quantidade' => '5', 'preco_unitario' => '9,00'];

    $tela->set('data.itens', $itens)
        ->call('save')
        ->assertHasNoFormErrors();

    expect((string) $brownie->refresh()->estoque_atual)->toBe('46.000')
        ->and((string) $cookie->refresh()->estoque_atual)->toBe('50.000')
        ->and((string) $palha->refresh()->estoque_atual)->toBe('45.000');
});

it('não recongela o custo ao editar só a observação de um pedido entregue', function () {
    $produto = produtoParaEncomenda();
    $pedido = encomendaAEntregar($produto, ['status' => StatusPedido::Entregue]);

    // o chocolate subiu depois da entrega
    $produto->forceFill(['custo_unitario' => '3.1000'])->save();

    Livewire::test(EditPedido::class, ['record' => $pedido->getRouteKey()])
        ->fillForm(['observacoes' => 'Entregue na portaria'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((string) $pedido->itens()->sole()->custo_unitario)->toBe('2.5000')
        ->and((string) $produto->refresh()->estoque_atual)->toBe('46.000');
});

it('congela o custo de agora no item que troca de produto', function () {
    $brownie = produtoParaEncomenda();
    $cookie = outroProduto('Cookie', '4.0000');
    $pedido = encomendaAEntregar($brownie, ['status' => StatusPedido::Entregue]);

    $tela = Livewire::test(EditPedido::class, ['record' => $pedido->getRouteKey()]);
    $chave = array_key_first($tela->instance()->form->getRawState()['itens']);

    $tela->set("data.itens.{$chave}.produto_id", $cookie->id)
        ->call('save')
        ->assertHasNoFormErrors();

    expect((string) $pedido->itens()->sole()->custo_unitario)->toBe('4.0000')
        ->and((string) $brownie->refresh()->estoque_atual)->toBe('50.000')
        ->and((string) $cookie->refresh()->estoque_atual)->toBe('46.000');
});

/*
 * A encomenda é produzida depois do pedido: o custo que vale é o da entrega.
 * Até lá o custo do item é provisório.
 */

it('congela na entrega o custo da fornada feita para a encomenda', function () {
    $produto = produtoParaEncomenda(); // custo 2,50 quando o pedido entrou
    $pedido = encomendaAEntregar($produto);

    // a fornada da encomenda saiu mais cara
    $produto->forceFill(['custo_unitario' => '3.2000'])->save();

    app(FinalizarPedido::class)->mudarStatus($pedido, StatusPedido::Entregue);

    expect((string) $pedido->itens()->sole()->custo_unitario)->toBe('3.2000');
});

it('não mexe no custo de uma encomenda entregue quando o produto encarece depois', function () {
    $produto = produtoParaEncomenda();
    $pedido = encomendaAEntregar($produto);
    app(FinalizarPedido::class)->mudarStatus($pedido, StatusPedido::Entregue);

    $produto->forceFill(['custo_unitario' => '9.0000'])->save();

    // marcar pagamento não passa pelo congelamento
    Livewire::test(ListPedidos::class)
        ->callAction(TestAction::make('alternarPagamento')->table($pedido));

    expect((string) $pedido->itens()->sole()->custo_unitario)->toBe('2.5000');
});

it('avisa ao entregar produto sem custo e recongela ao entregar de novo', function () {
    $produto = produtoParaEncomenda();
    $produto->forceFill(['custo_unitario' => '0'])->save();
    $pedido = encomendaAEntregar($produto);

    Livewire::test(ListPedidos::class)
        ->callAction(TestAction::make('entregar')->table($pedido))
        ->assertNotified($pedido->identificacao.' entregue sem custo');

    expect((string) $pedido->itens()->sole()->custo_unitario)->toBe('0.0000');

    // a produção foi lançada depois da entrega
    $produto->forceFill(['custo_unitario' => '2.8000'])->save();

    Livewire::test(ListPedidos::class)
        ->callAction(TestAction::make('desfazerEntrega')->table($pedido))
        ->callAction(TestAction::make('entregar')->table($pedido));

    expect((string) $pedido->itens()->sole()->custo_unitario)->toBe('2.8000');
});
