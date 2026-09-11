<?php

use Illuminate\Support\ConfigurationUrlParser;

/**
 * A integração do Neon com a Vercel não cria DB_HOST e companhia: ela entrega
 * uma connection string. Estes testes travam o entendimento dela.
 */
it('extrai host, banco e credenciais da connection string do Neon', function () {
    $config = (new ConfigurationUrlParser)->parseConfiguration([
        'driver' => 'pgsql',
        'url' => 'postgresql://dono:segredo@ep-teste-pooler.sa-east-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require',
    ]);

    expect($config['driver'])->toBe('pgsql')
        ->and($config['host'])->toBe('ep-teste-pooler.sa-east-1.aws.neon.tech')
        ->and($config['database'])->toBe('neondb')
        ->and($config['username'])->toBe('dono')
        ->and($config['password'])->toBe('segredo')
        // sem sslmode=require o Neon recusa a conexão
        ->and($config['sslmode'])->toBe('require');
});

it('mantém os prepares emulados ao usar connection string', function () {
    $config = (new ConfigurationUrlParser)->parseConfiguration([
        'driver' => 'pgsql',
        'url' => 'postgresql://dono:segredo@ep-teste-pooler.sa-east-1.aws.neon.tech/neondb?sslmode=require',
        'options' => [PDO::ATTR_EMULATE_PREPARES => true],
    ]);

    // a URL não pode apagar o que resolve o PgBouncer abortando transações
    expect($config['options'][PDO::ATTR_EMULATE_PREPARES])->toBeTrue();
});

it('aceita os nomes de variável que a integração do Neon cria', function () {
    $nomes = ['DB_URL', 'DATABASE_URL', 'POSTGRES_URL', 'DATABASE_URL_UNPOOLED', 'POSTGRES_URL_NON_POOLING'];

    $config = file_get_contents(config_path('database.php'));

    foreach ($nomes as $nome) {
        expect($config)->toContain("env('{$nome}')");
    }
});
