<?php

use App\Filament\Resources\Insumos\Pages\CreateInsumo;
use App\Filament\Resources\Insumos\Pages\EditInsumo;
use App\Models\Insumo;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(fn () => actingAs(User::factory()->create()));

it('grava o valor com máscara brasileira no formato do banco', function () {
    Livewire::test(CreateInsumo::class)
        ->fillForm([
            'nome' => 'Chocolate meio amargo',
            'unidade_base' => 'g',
            'estoque_minimo' => '2.000,000',
            'estoque_atual' => '5.000,000',
            'custo_medio' => '0,0348',
            'ativo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $insumo = Insumo::firstWhere('nome', 'Chocolate meio amargo');

    expect($insumo)->not->toBeNull()
        ->and((string) $insumo->estoque_atual)->toBe('5000.000')
        ->and((string) $insumo->custo_medio)->toBe('0.0348')
        ->and((string) $insumo->estoque_minimo)->toBe('2000.000');
});

it('exige nome e unidade', function () {
    Livewire::test(CreateInsumo::class)
        ->fillForm(['nome' => null, 'unidade_base' => null])
        ->call('create')
        ->assertHasFormErrors(['nome' => 'required', 'unidade_base' => 'required']);
});

it('devolve o valor formatado em pt-BR ao editar', function () {
    $insumo = Insumo::create([
        'nome' => 'Farinha de trigo',
        'unidade_base' => 'g',
        'estoque_atual' => '12500.500',
        'custo_medio' => '0.0052',
        'estoque_minimo' => '3000',
    ]);

    Livewire::test(EditInsumo::class, ['record' => $insumo->getRouteKey()])
        ->assertFormSet([
            'estoque_minimo' => '3.000,000',
            'estoque_atual' => '12.500,500',
            'custo_medio' => '0,0052',
        ]);
});
