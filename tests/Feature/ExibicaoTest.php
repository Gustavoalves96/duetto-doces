<?php

use App\Support\Decimal;

/**
 * Como os números aparecem na tela.
 *
 * O banco guarda 3 casas na quantidade e 4 no custo porque o cálculo precisa.
 * A tela não: "15,000 un" faz um brasileiro ler quinze mil, e ninguém precifica
 * brownie em R$ 0,9333.
 */
it('tira os zeros à toa da quantidade', function (string $guardado, string $esperado) {
    expect(Decimal::paraBrEnxuto($guardado))->toBe($esperado);
})->with([
    'inteiro' => ['15', '15'],
    'uma unidade' => ['1', '1'],
    'meia fornada' => ['0.5', '0,5'],
    'milhar' => ['1200', '1.200'],
    'com centavos' => ['15.75', '15,75'],
    'tres casas reais' => ['2.125', '2,125'],
    'zero' => ['0', '0'],
]);

it('mostra dinheiro com duas casas', function () {
    expect(Decimal::paraRealLegivel('0.9333'))->toBe('R$ 0,93')
        ->and(Decimal::paraRealLegivel('13.70'))->toBe('R$ 13,70')
        ->and(Decimal::paraRealLegivel('14'))->toBe('R$ 14,00')
        ->and(Decimal::paraRealLegivel('1234.5'))->toBe('R$ 1.234,50');
});

it('abre para quatro casas quando duas mostrariam zero', function () {
    // custo por grama de um insumo barato não pode virar R$ 0,00 na tela
    expect(Decimal::paraRealLegivel('0.0035'))->toBe('R$ 0,0035')
        ->and(Decimal::paraRealLegivel('0.004'))->toBe('R$ 0,0040');
});

it('nao mexe no valor guardado, so na exibicao', function () {
    // a precisão do cálculo continua intacta
    expect(Decimal::custo('0.93333'))->toBe('0.9333')
        ->and(Decimal::quantidade('15'))->toBe('15.000');
});
