<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * O .env desta máquina aponta para o banco real. O RefreshDatabase roda
     * logo depois daqui e apagaria tudo se a conexão não fosse a de teste
     * (config em cache ou DB_* exportado no shell vencem o phpunit.xml).
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $conexao = config('database.connections.'.config('database.default'), []);

        if (
            ($conexao['driver'] ?? null) !== 'sqlite'
            || ($conexao['database'] ?? null) !== ':memory:'
            || filled($conexao['url'] ?? null)
        ) {
            throw new RuntimeException(
                'Testes só rodam em SQLite em memória. Conexão atual: '
                .config('database.default').' / '.($conexao['database'] ?? '?')
                .'. Apague bootstrap/cache/config.php e confira as variáveis DB_* do shell.'
            );
        }
    }
}
