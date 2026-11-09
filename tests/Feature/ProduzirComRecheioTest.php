<?php

use App\Filament\Resources\Receitas\Pages\EditReceita;
use App\Filament\Resources\Receitas\Pages\ListReceitas;
use App\Models\Insumo;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Mesmo caminho da tela: modal "Produzir" com 7 de 20 brownies com Nutella.
 */

beforeEach(fn () => actingAs(User::factory()->create()));

function fichaComNutella(): array
{
    $chocolate = Insumo::create([
        'nome' => 'Chocolate', 'unidade_base' => 'g',
        'estoque_atual' => '5000', 'custo_medio' => '0.0600', 'estoque_minimo' => '0',
    ]);
    $nutella = Insumo::create([
        'nome' => 'Nutella', 'unidade_base' => 'g',
        'estoque_atual' => '1000', 'custo_medio' => '0.1000', 'estoque_minimo' => '0',
    ]);

    $tradicional = Produto::create(['nome' => 'Brownie tradicional', 'preco_venda' => '8.00']);
    $comNutella = Produto::create(['nome' => 'Brownie com Nutella', 'preco_venda' => '12.00']);

    $receita = Receita::create(['produto_id' => $tradicional->id, 'rendimento' => '20']);
    $receita->itens()->create(['insumo_id' => $chocolate->id, 'quantidade' => '500']); // R$ 30
    $recheio = $receita->recheios()->create([
        'produto_id' => $comNutella->id, 'insumo_id' => $nutella->id, 'quantidade' => '20',
    ]);

    return compact('nutella', 'tradicional', 'comNutella', 'receita', 'recheio');
}

it('produz pela tela dividindo a fornada no recheio', function () {
    ['nutella' => $nutella, 'tradicional' => $tradicional, 'comNutella' => $comNutella,
        'receita' => $receita, 'recheio' => $recheio] = fichaComNutella();

    Livewire::test(ListReceitas::class)
        ->callAction(TestAction::make('produzir')->table($receita), [
            'multiplicador' => '1',
            'recheios' => [$recheio->id => '7'],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Produção registrada');

    expect((string) $tradicional->refresh()->estoque_atual)->toBe('13.000')
        ->and((string) $comNutella->refresh()->estoque_atual)->toBe('7.000')
        ->and((string) $comNutella->custo_unitario)->toBe('3.5000')
        ->and((string) $nutella->refresh()->estoque_atual)->toBe('860.000');
});

it('nao registra nada quando os recheios passam do rendimento', function () {
    ['tradicional' => $tradicional, 'receita' => $receita, 'recheio' => $recheio] = fichaComNutella();

    Livewire::test(ListReceitas::class)
        ->callAction(TestAction::make('produzir')->table($receita), [
            'multiplicador' => '1',
            'recheios' => [$recheio->id => '25'],
        ])
        ->assertNotified('Produção não registrada');

    expect($receita->producoes()->count())->toBe(0)
        ->and((string) $tradicional->refresh()->estoque_atual)->toBe('0.000');
});

it('ficha sem recheio produz como antes', function () {
    ['tradicional' => $tradicional, 'receita' => $receita] = fichaComNutella();
    $receita->recheios()->delete();

    Livewire::test(ListReceitas::class)
        ->callAction(TestAction::make('produzir')->table($receita), ['multiplicador' => '1'])
        ->assertHasNoFormErrors();

    expect((string) $tradicional->refresh()->estoque_atual)->toBe('20.000');
});

it('cadastra o recheio na ficha e recusa o mesmo produto da ficha', function () {
    ['tradicional' => $tradicional, 'comNutella' => $comNutella, 'nutella' => $nutella,
        'receita' => $receita] = fichaComNutella();
    $receita->recheios()->delete();

    Livewire::test(EditReceita::class, ['record' => $receita->getRouteKey()])
        ->set('data.recheios', ['novo' => [
            'produto_id' => $tradicional->id, 'insumo_id' => $nutella->id, 'quantidade' => '20',
        ]])
        ->call('save')
        ->assertHasFormErrors(['recheios.novo.produto_id']);

    Livewire::test(EditReceita::class, ['record' => $receita->getRouteKey()])
        ->set('data.recheios', ['novo' => [
            'produto_id' => $comNutella->id, 'insumo_id' => $nutella->id, 'quantidade' => '20',
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($receita->recheios()->sole())
        ->produto_id->toBe($comNutella->id)
        ->and((string) $receita->recheios()->sole()->quantidade)->toBe('20.000');
});
