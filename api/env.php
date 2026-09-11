<?php

/**
 * Diagnóstico de ambiente que NÃO depende do Laravel. TEMPORÁRIO.
 *
 * A rota /diagnostico dentro da aplicação é inútil quando o próprio boot do
 * Laravel falha: ela morre pelo mesmo motivo que deveria investigar. Esta
 * função é PHP puro e roda antes de qualquer coisa do framework.
 *
 * Regra de ouro daqui: nunca imprimir VALOR de variável. Só os nomes presentes,
 * o resultado de cada checagem e, no caso de exceção, uma mensagem já limpa de
 * host e credencial.
 */
header('Content-Type: application/json; charset=utf-8');

$ambiente = $_ENV + $_SERVER;

/** Esconde qualquer credencial que apareça numa mensagem de erro. */
$limpar = static function (string $texto) use ($ambiente): string {
    foreach ($ambiente as $valor) {
        if (is_string($valor) && strlen($valor) >= 8) {
            $texto = str_replace($valor, '[oculto]', $texto);
        }
    }

    return preg_replace('#://[^@\s]+@#', '://[oculto]@', $texto);
};

// ---- 1. quais variáveis existem (só os nomes) ----
$interessantes = [];
foreach (array_keys($ambiente) as $nome) {
    if (preg_match('/(DB|DATABASE|POSTGRES|PG|APP_KEY|APP_URL|APP_ENV|APP_DEBUG|SESSION|CACHE|LOG_|VIEW_)/i', (string) $nome)) {
        $interessantes[] = $nome;
    }
}
sort($interessantes);

// ---- 2. o runtime tem o que o projeto precisa? ----
$extensoes = [];
foreach (['pdo_pgsql', 'pdo_sqlite', 'bcmath', 'mbstring', 'intl', 'openssl', 'zip'] as $ext) {
    $extensoes[$ext] = extension_loaded($ext) ? 'ok' : 'AUSENTE';
}

// ---- 3. /tmp é gravável? é lá que o Blade compila ----
$tmp = '/tmp/teste_escrita_'.bin2hex(random_bytes(4));
$escrita = @file_put_contents($tmp, 'x') !== false ? 'ok' : 'FALHOU';
@unlink($tmp);

// ---- 4. a connection string conecta mesmo? ----
$conexao = 'nenhuma connection string encontrada';
foreach (['DB_URL', 'DATABASE_URL', 'POSTGRES_URL', 'DATABASE_URL_UNPOOLED', 'POSTGRES_URL_NON_POOLING'] as $chave) {
    if (empty($ambiente[$chave])) {
        continue;
    }

    $partes = parse_url((string) $ambiente[$chave]);

    if ($partes === false || ! isset($partes['host'])) {
        $conexao = "{$chave}: nao consegui interpretar a URL";
        break;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
        $partes['host'],
        $partes['port'] ?? 5432,
        ltrim($partes['path'] ?? '/postgres', '/'),
    );

    try {
        new PDO($dsn, $partes['user'] ?? '', $partes['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $conexao = "{$chave}: CONECTOU (pooler: ".(str_contains($partes['host'], '-pooler') ? 'sim' : 'nao').')';
    } catch (Throwable $e) {
        $conexao = "{$chave}: FALHOU — ".$limpar($e->getMessage());
    }

    break;
}

// ---- 5. o Laravel sobe? aqui sai o erro real que o 500 esconde ----
$laravel = 'nao testado';
try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $laravel = 'bootstrap OK';

    try {
        Illuminate\Support\Facades\DB::select('select 1');
        $laravel .= ' | banco: CONECTOU';
    } catch (Throwable $e) {
        $laravel .= ' | banco: '.get_class($e).' — '.$limpar($e->getMessage());
    }

    try {
        encrypt('teste');
        $laravel .= ' | criptografia: OK';
    } catch (Throwable $e) {
        $laravel .= ' | criptografia: '.class_basename($e);
    }
} catch (Throwable $e) {
    $laravel = 'bootstrap FALHOU: '.get_class($e).' — '.$limpar($e->getMessage());
}

echo json_encode([
    'variaveis_presentes' => $interessantes,
    'extensoes' => $extensoes,
    'tmp_gravavel' => $escrita,
    'conexao_direta' => $conexao,
    'laravel' => $laravel,
    'php' => PHP_VERSION,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
