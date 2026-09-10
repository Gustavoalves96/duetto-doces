<?php

use App\Filament\Pages\Relatorios;
use App\Filament\Resources\Clientes\Pages as Clientes;
use App\Filament\Resources\Compras\Pages as Compras;
use App\Filament\Resources\Despesas\Pages as Despesas;
use App\Filament\Resources\Insumos\Pages as Insumos;
use App\Filament\Resources\Movimentacoes\Pages as Movimentacoes;
use App\Filament\Resources\Pedidos\Pages as Pedidos;
use App\Filament\Resources\Produtos\Pages as Produtos;
use App\Filament\Resources\Receitas\Pages as Receitas;
use App\Filament\Widgets\EncomendasDaSemana;
use App\Filament\Widgets\FaturamentoDiario;
use App\Filament\Widgets\ResumoDoMes;
use App\Models\User;
use Filament\Pages\Dashboard;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => actingAs(User::factory()->create()));

it('abre a listagem de cada recurso', function (string $pagina) {
    Livewire::test($pagina)->assertOk();
})->with([
    'insumos' => Insumos\ListInsumos::class,
    'produtos' => Produtos\ListProdutos::class,
    'movimentações' => Movimentacoes\ListMovimentacoes::class,
    'compras' => Compras\ListCompras::class,
    'receitas' => Receitas\ListReceitas::class,
    'pedidos' => Pedidos\ListPedidos::class,
    'clientes' => Clientes\ListClientes::class,
    'despesas' => Despesas\ListDespesas::class,
]);

it('abre o formulário de criação de cada recurso', function (string $pagina) {
    Livewire::test($pagina)->assertOk();
})->with([
    'insumos' => Insumos\CreateInsumo::class,
    'produtos' => Produtos\CreateProduto::class,
    'compras' => Compras\CreateCompra::class,
    'receitas' => Receitas\CreateReceita::class,
    'pedidos' => Pedidos\CreatePedido::class,
    'clientes' => Clientes\CreateCliente::class,
    'despesas' => Despesas\CreateDespesa::class,
]);

it('abre o painel pela rota', function () {
    get('/admin')->assertSuccessful();
});

it('usa o nome da loja no painel', function () {
    expect(config('app.name'))->toBe('Duetto Doces');
});

it('abre o dashboard com os widgets', function () {
    Livewire::test(Dashboard::class)->assertOk();
});

it('renderiza cada widget do dashboard', function (string $widget) {
    Livewire::test($widget)->assertOk();
})->with([
    'resumo do mês' => ResumoDoMes::class,
    'faturamento diário' => FaturamentoDiario::class,
    'encomendas' => EncomendasDaSemana::class,
]);

it('abre a página de relatórios', function () {
    Livewire::test(Relatorios::class)->assertOk();
});
