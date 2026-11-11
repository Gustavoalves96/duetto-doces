<?php

use App\Services\PrecoSugerido;

beforeEach(function () {
    config([
        'confeitaria.indiretos_percentual' => '10',
        'confeitaria.margem_desejada_percentual' => '50',
    ]);
});

it('soma os indiretos ao custo e aplica a margem sobre o preco', function () {
    // 2,00 + 10% = 2,20 ; para sobrar 50% do preço: 2,20 / 0,5 = 4,40
    expect(app(PrecoSugerido::class)->calcular('2.0000'))->toBe('4.40');
});

it('arredonda a sugestao para centavos', function () {
    // 1,7000 + 10% = 1,87 ; / 0,5 = 3,74
    expect(app(PrecoSugerido::class)->calcular('1.7000'))->toBe('3.74');
});

it('nao sugere preco sem custo', function () {
    expect(app(PrecoSugerido::class)->calcular('0'))->toBeNull()
        ->and(app(PrecoSugerido::class)->paraExibir('0'))->toBe('—');
});

it('nao sugere preco com margem de 100 por cento ou mais', function () {
    config(['confeitaria.margem_desejada_percentual' => '100']);

    expect(app(PrecoSugerido::class)->calcular('2.0000'))->toBeNull();
});
