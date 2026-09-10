<?php

use App\Filament\Pages\Relatorios;
use App\Filament\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Resources\Compras\Pages\CreateCompra;
use App\Filament\Resources\Insumos\Pages\ListInsumos;
use App\Filament\Resources\Pedidos\Pages\CreatePedido;
use App\Filament\Resources\Pedidos\Pages\ListPedidos;
use App\Filament\Resources\Produtos\Pages\ListProdutos;
use App\Filament\Resources\Receitas\Pages\ListReceitas;
use App\Filament\Widgets\EncomendasDaSemana;
use App\Filament\Widgets\FaturamentoDiario;
use App\Filament\Widgets\ResumoDoMes;
use App\Models\Produto;
use App\Models\User;
use Filament\Pages\Dashboard;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed();
    actingAs(User::first());
});

it('renderiza todas as telas com os dados de exemplo', function (string $pagina) {
    Livewire::test($pagina)->assertOk();
})->with([
    'insumos' => ListInsumos::class,
    'produtos' => ListProdutos::class,
    'receitas' => ListReceitas::class,
    'pedidos' => ListPedidos::class,
    'relatórios' => Relatorios::class,
    'resumo do mês' => ResumoDoMes::class,
    'faturamento diário' => FaturamentoDiario::class,
    'encomendas' => EncomendasDaSemana::class,
]);

it('mostra o custo calculado dos produtos na listagem', function () {
    $brownie = Produto::firstWhere('nome', 'Brownie tradicional');

    // o custo nasceu da ficha técnica, não foi digitado
    expect((float) $brownie->custo_unitario)->toBeGreaterThan(1.0)
        ->and((float) $brownie->custo_unitario)->toBeLessThan(3.0);

    Livewire::test(ListProdutos::class)
        ->assertCanSeeTableRecords(Produto::all())
        ->assertOk();
});

it('aplica a máscara de dinheiro no HTML do formulário', function () {
    $html = Livewire::test(CreatePedido::class)->html();

    // x-mask:dynamic com o helper $money do plugin de máscara do Alpine.
    // As aspas dentro do atributo têm que ser simples: se virarem duplas,
    // o atributo HTML fecha no meio e a máscara para de funcionar em silêncio.
    $esperado = <<<'HTML'
        x-mask:dynamic="$money($input, ',', '.', 2)"
        HTML;

    expect($html)->toContain(trim($esperado));
});

it('aplica a máscara de quantidade com 3 casas', function () {
    $html = Livewire::test(CreateCompra::class)->html();

    $esperado = <<<'HTML'
        x-mask:dynamic="$money($input, ',', '.', 3)"
        HTML;

    expect($html)->toContain(trim($esperado));
});

it('aplica a máscara de telefone no cadastro de cliente', function () {
    $html = Livewire::test(CreateCliente::class)->html();

    // máscara dinâmica: 11 dígitos vira celular, 10 fica fixo
    expect($html)->toContain('x-mask:dynamic')
        ->and($html)->toContain('(99) 99999-9999');
});

it('mostra os totais do período na página de relatórios', function () {
    $dados = Livewire::test(Relatorios::class)->instance()->getDadosProperty();

    expect($dados['resumo']['pedidos'])->toBeGreaterThan(0)
        ->and((float) $dados['resumo']['faturamento_bruto'])->toBeGreaterThan(0)
        ->and($dados['produtos'])->not->toBeEmpty()
        ->and($dados['despesas'])->not->toBeEmpty()
        ->and((float) $dados['estoque']['total'])->toBeGreaterThan(0);
});

it('nao deixa nenhum produto com estoque negativo no seed', function () {
    Produto::all()->each(
        fn (Produto $p) => expect((float) $p->estoque_atual)
            ->toBeGreaterThanOrEqual(0, "{$p->nome} ficou com estoque negativo")
    );
});

it('empilha os itens da compra em telas estreitas', function () {
    $html = Livewire::test(CreateCompra::class)->html();

    // 1 coluna no celular, 2 no tablet, 12 só a partir de xl — um columns(12)
    // simples viraria ['lg' => 12] e espremeria o select de unidade a 1024px
    expect($html)->toContain('--cols-default: repeat(1, minmax(0, 1fr))')
        ->and($html)->toContain('--cols-md: repeat(2, minmax(0, 1fr))')
        ->and($html)->toContain('--cols-xl: repeat(12, minmax(0, 1fr))');
});

it('deixa o gráfico por último no dashboard e com altura contida', function () {
    $ordem = collect(app(Dashboard::class)->getWidgets())
        ->map(fn ($widget) => class_basename($widget))
        ->values()
        ->all();

    expect($ordem)->toBe(['ResumoDoMes', 'EncomendasDaSemana', 'FaturamentoDiario']);

    $chart = Livewire::test(FaturamentoDiario::class)->html();

    // sem teto o Chart.js estica e o gráfico toma meia tela no desktop
    expect($chart)->toContain('max-height: 240px');
});
