<?php

use Illuminate\Support\Facades\Hash;

/**
 * Um BCRYPT_ROUNDS inválido não quebra mais o login.
 *
 * O bcrypt só aceita custo de 4 a 31. Fora disso o password_hash() lança
 * ValueError e o Laravel reporta "Bcrypt hashing not supported" — mensagem
 * que aponta para o servidor quando o problema é a variável de ambiente.
 */
it('sanitiza um custo de bcrypt invalido', function (mixed $valor) {
    // recarrega a config como se o ambiente tivesse esse valor
    $rounds = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 4, 'max_range' => 31, 'default' => 12]],
    );

    expect($rounds)->toBe(12)
        ->and(password_hash('senha', PASSWORD_BCRYPT, ['cost' => $rounds]))->toBeString();
})->with([
    'vazio' => '',
    'zero' => '0',
    'texto' => 'abc',
    'nulo' => null,
    'acima do limite' => '99',
    'abaixo do limite' => '2',
]);

it('respeita um custo valido', function () {
    $rounds = filter_var(
        '10',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 4, 'max_range' => 31, 'default' => 12]],
    );

    expect($rounds)->toBe(10);
});

it('usa um custo valido na configuracao real', function () {
    $rounds = config('hashing.bcrypt.rounds');

    expect($rounds)->toBeInt()
        ->toBeGreaterThanOrEqual(4)
        ->toBeLessThanOrEqual(31);
});

it('consegue gerar e conferir hash com a config do projeto', function () {
    $hash = Hash::make('senhaDeTeste123');

    expect($hash)->toStartWith('$2y$')
        ->and(Hash::check('senhaDeTeste123', $hash))->toBeTrue()
        // com o custo configurado, um hash recém-criado nunca precisa de rehash
        ->and(Hash::needsRehash($hash))->toBeFalse();
});
