<?php

use App\Filament\Resources\Insumos\Pages\CreateInsumo;
use App\Models\Insumo;
use App\Models\User;
use App\Support\Decimal;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Separador de milhar sem vírgula decimal.
 *
 * A máscara $money do Alpine só agrupa milhares: quem digita 2000 vê "2.000".
 * O Decimal::de() lia esse ponto como decimal inglês e gravava 2 — mil vezes
 * menos. Com dois pontos ("1.000.000") o valor não era numérico e virava zero
 * em silêncio, que é pior ainda.
 */
it('interpreta milhar sem virgula decimal', function (string $mascarado, string $esperado) {
    expect(Decimal::deBr($mascarado))->toBe($esperado);
})->with([
    'dois mil' => ['2.000', '2000'],
    'dois mil e quinhentos' => ['2.500', '2500'],
    'um milhao' => ['1.000.000', '1000000'],
    'trinta e cinco mil' => ['35.000', '35000'],
    'com decimal' => ['2.000,5', '2000.5'],
    'milhar e centavos' => ['1.234,56', '1234.56'],
    'so decimal' => ['0,5', '0.5'],
    'quatro casas' => ['17,5175', '17.5175'],
    'sem separador' => ['2000', '2000'],
    'negativo' => ['-1.500,25', '-1500.25'],
    'vazio' => ['', '0'],
]);

it('nao quebra os valores que vem do banco', function () {
    // a de() continua servindo para valor de máquina, onde o ponto é decimal
    expect(Decimal::de('1234.56'))->toBe('1234.56')
        ->and(Decimal::de('0.0348'))->toBe('0.0348');
});

it('grava a quantidade certa quando o usuario digita milhar', function () {
    actingAs(User::factory()->create());

    Livewire::test(CreateInsumo::class)
        ->fillForm([
            'nome' => 'Nescau',
            'unidade_base' => 'g',
            'estoque_minimo' => '500',
            // exatamente o que a máscara mostra ao digitar 2000
            'estoque_atual' => '2.000',
            'custo_medio' => '0,0175',
            'ativo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $insumo = Insumo::firstWhere('nome', 'Nescau');

    // 2000 gramas, e não 2
    expect((string) $insumo->estoque_atual)->toBe('2000.000')
        ->and((string) $insumo->estoque_minimo)->toBe('500.000')
        ->and((string) $insumo->custo_medio)->toBe('0.0175')
        // 2000 g a R$ 0,0175 = R$ 35,00 parados
        ->and($insumo->valorEmEstoque())->toBe('35.00');
});

it('grava dinheiro na casa dos milhares sem dividir por mil', function () {
    actingAs(User::factory()->create());

    Livewire::test(CreateInsumo::class)
        ->fillForm([
            'nome' => 'Chocolate em barra',
            'unidade_base' => 'g',
            'estoque_minimo' => '0',
            'estoque_atual' => '1.000.000',
            'custo_medio' => '0,0500',
            'ativo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $insumo = Insumo::firstWhere('nome', 'Chocolate em barra');

    // um milhão de gramas, não zero
    expect((string) $insumo->estoque_atual)->toBe('1000000.000')
        ->and($insumo->valorEmEstoque())->toBe('50000.00');
});
