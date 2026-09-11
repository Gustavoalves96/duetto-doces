<?php

use App\Filament\Resources\Compras\Pages\CreateCompra;
use App\Models\Compra;
use App\Models\Insumo;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Conversão da unidade de compra para a unidade de estoque.
 *
 * O CLAUDE.md é explícito: "a compra é em kg, a receita usa 180 g — converta na
 * entrada". O seletor de unidade estava com dehydrated(false), então seu valor
 * nunca chegava no callback de conversão e o fallback tratava tudo como grama.
 * Uma compra de 1 kg virava 1 g, e o custo médio saía mil vezes maior.
 */
beforeEach(function () {
    actingAs(User::factory()->create());

    $this->nescau = Insumo::create([
        'nome' => 'Nescau',
        'unidade_base' => 'g',
        'estoque_minimo' => '500',
    ]);
});

it('converte quilo para grama ao lancar a compra', function () {
    Livewire::test(CreateCompra::class)
        ->fillForm([
            'fornecedor' => 'Atacadão',
            'data' => now()->toDateString(),
            'itens' => [
                [
                    'insumo_id' => $this->nescau->id,
                    'unidade_entrada' => 'kg',
                    'quantidade' => '1',
                    'valor_total' => '35,00',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = Compra::first()->itens()->sole();

    // 1 kg = 1000 g, e não 1 g
    expect((string) $item->quantidade)->toBe('1000.000');

    $this->nescau->refresh();

    expect((string) $this->nescau->estoque_atual)->toBe('1000.000')
        // R$ 35,00 / 1000 g = R$ 0,035 por grama
        ->and((string) $this->nescau->custo_medio)->toBe('0.0350')
        ->and($this->nescau->valorEmEstoque())->toBe('35.00');
});

it('nao converte quando a unidade informada ja e a de estoque', function () {
    Livewire::test(CreateCompra::class)
        ->fillForm([
            'fornecedor' => 'Mercado',
            'data' => now()->toDateString(),
            'itens' => [
                [
                    'insumo_id' => $this->nescau->id,
                    'unidade_entrada' => 'g',
                    'quantidade' => '800',
                    'valor_total' => '28,00',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect((string) Compra::first()->itens()->sole()->quantidade)->toBe('800.000')
        ->and((string) $this->nescau->refresh()->custo_medio)->toBe('0.0350');
});

it('converte litro para mililitro', function () {
    $essencia = Insumo::create([
        'nome' => 'Essência de baunilha',
        'unidade_base' => 'ml',
        'estoque_minimo' => '100',
    ]);

    Livewire::test(CreateCompra::class)
        ->fillForm([
            'fornecedor' => 'Casa dos Doces',
            'data' => now()->toDateString(),
            'itens' => [
                [
                    'insumo_id' => $essencia->id,
                    'unidade_entrada' => 'l',
                    'quantidade' => '0,5',
                    'valor_total' => '28,00',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect((string) $essencia->refresh()->estoque_atual)->toBe('500.000')
        ->and((string) $essencia->custo_medio)->toBe('0.0560');
});

it('nao grava a unidade de entrada como coluna do item', function () {
    Livewire::test(CreateCompra::class)
        ->fillForm([
            'fornecedor' => 'Atacadão',
            'data' => now()->toDateString(),
            'itens' => [
                [
                    'insumo_id' => $this->nescau->id,
                    'unidade_entrada' => 'kg',
                    'quantidade' => '2',
                    'valor_total' => '70,00',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // a coluna não existe em compra_itens; se vazasse, o insert quebraria
    expect(Compra::first()->itens()->sole()->getAttributes())
        ->not->toHaveKey('unidade_entrada');
});

it('combina conversao com separador de milhar', function () {
    Livewire::test(CreateCompra::class)
        ->fillForm([
            'fornecedor' => 'Atacadão',
            'data' => now()->toDateString(),
            'itens' => [
                [
                    'insumo_id' => $this->nescau->id,
                    'unidade_entrada' => 'g',
                    // o que a máscara mostra ao digitar 2000
                    'quantidade' => '2.000',
                    'valor_total' => '70,00',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect((string) $this->nescau->refresh()->estoque_atual)->toBe('2000.000')
        ->and((string) $this->nescau->custo_medio)->toBe('0.0350');
});
