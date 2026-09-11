<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de hash padrão
    |--------------------------------------------------------------------------
    |
    | Algoritmo usado para gerar o hash das senhas. Suportados: "bcrypt",
    | "argon" e "argon2id".
    |
    */

    'driver' => env('HASH_DRIVER', 'bcrypt'),

    /*
    |--------------------------------------------------------------------------
    | Opções do Bcrypt
    |--------------------------------------------------------------------------
    |
    | O "rounds" é sanitizado de propósito, e não lido cru do ambiente.
    |
    | O bcrypt só aceita custo entre 4 e 31. Uma variável de ambiente vazia,
    | ausente do tipo errado ou fora dessa faixa faz o password_hash() lançar
    | ValueError, que o Laravel converte na mensagem enganosa "Bcrypt hashing
    | not supported" — como se o servidor não soubesse fazer bcrypt.
    |
    | O sintoma é traiçoeiro: o login ACEITA a senha (o password_verify não
    | depende do custo) e só quebra logo depois, quando o Laravel decide
    | re-hashear a senha porque o custo do hash guardado não bate com o
    | configurado. Resultado: 500 no submit do login, com a senha correta.
    |
    */

    'bcrypt' => [
        'rounds' => filter_var(
            env('BCRYPT_ROUNDS', 12),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 4, 'max_range' => 31, 'default' => 12]],
        ),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Opções do Argon
    |--------------------------------------------------------------------------
    |
    | Só usadas se HASH_DRIVER for argon. Mantidas nos padrões do framework.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash automático no login
    |--------------------------------------------------------------------------
    |
    | Quando ligado, o Laravel regrava o hash da senha no login se o custo
    | configurado tiver mudado. É desejável, e continua ligado — o problema
    | acima era o custo inválido, não o rehash em si.
    |
    */

    'rehash_on_login' => true,

];
