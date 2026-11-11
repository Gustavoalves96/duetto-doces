<?php

use App\Filament\Resources\Insumos\Pages\EditInsumo;
use App\Filament\Resources\Produtos\Pages\EditProduto;
use App\Filament\Resources\Receitas\Pages\EditReceita;
use App\Models\Insumo;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\User;
use App\Services\ExecutarProducao;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Apagar insumo ou produto levaria o histórico de movimentações junto.
 * O que sai da lista sai pelo campo "em uso", não por exclusão.
 */

beforeEach(fn () => actingAs(User::factory()->create()));

function fichaSimples(): Receita
{
    $farinha = Insumo::create([
        'nome' => 'Farinha', 'unidade_base' => 'g',
        'estoque_atual' => '1000', 'custo_medio' => '0.0050', 'estoque_minimo' => '0',
    ]);
    $produto = Produto::create(['nome' => 'Cookie', 'preco_venda' => '6.00']);
    $receita = Receita::create(['produto_id' => $produto->id, 'rendimento' => '10']);
    $receita->itens()->create(['insumo_id' => $farinha->id, 'quantidade' => '200']);

    return $receita;
}

it('nao oferece excluir insumo nem produto', function () {
    $receita = fichaSimples();

    Livewire::test(EditInsumo::class, ['record' => $receita->itens()->sole()->insumo_id])
        ->assertActionDoesNotExist('delete');

    Livewire::test(EditProduto::class, ['record' => $receita->produto_id])
        ->assertActionDoesNotExist('delete');
});

it('so deixa excluir ficha tecnica que nunca produziu', function () {
    $receita = fichaSimples();

    Livewire::test(EditReceita::class, ['record' => $receita->getRouteKey()])
        ->assertActionVisible('delete');

    app(ExecutarProducao::class)->executar($receita, '1');

    Livewire::test(EditReceita::class, ['record' => $receita->getRouteKey()])
        ->assertActionHidden('delete');
});
