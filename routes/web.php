<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

/**
 * O sistema é só o painel. Não existe site público, então a raiz manda direto
 * para o /admin — a welcome page do Laravel depende de um build do Vite que
 * este projeto não usa e daria 500 em produção.
 */
Route::get('/', fn () => Redirect::to('/admin'));

/**
 * Diagnóstico de ambiente. TEMPORÁRIO — remover assim que o deploy estabilizar.
 *
 * Existe porque numa lambda não há como abrir um terminal e olhar o que está
 * configurado. Responde apenas se cada variável EXISTE e se a conexão sobe.
 * Nunca imprime valor de variável, nem host, nem mensagem crua de exceção:
 * a falha é reduzida a uma categoria conhecida, para não vazar credencial
 * numa página pública.
 */
Route::get('/diagnostico', function () {
    $definida = fn (string $chave) => filled(env($chave)) ? 'definida' : 'AUSENTE';

    $variaveis = [];
    foreach ([
        'APP_KEY', 'APP_URL', 'APP_ENV',
        'DB_CONNECTION',
        'DB_URL', 'DATABASE_URL', 'POSTGRES_URL',
        'DATABASE_URL_UNPOOLED', 'POSTGRES_URL_NON_POOLING',
        'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        'SESSION_DRIVER', 'CACHE_STORE',
    ] as $chave) {
        $variaveis[$chave] = $definida($chave);
    }

    // como a conexão ficou DEPOIS de resolver a connection string
    $conexao = config('database.connections.'.config('database.default'));
    $resolvido = [
        'driver' => $conexao['driver'] ?? '(nenhum)',
        'host_definido' => filled($conexao['host'] ?? null) ? 'sim' : 'NAO',
        'usa_pooler' => str_contains((string) ($conexao['host'] ?? ''), '-pooler') ? 'sim' : 'nao',
        'banco_definido' => filled($conexao['database'] ?? null) ? 'sim' : 'NAO',
        'sslmode' => $conexao['sslmode'] ?? '(nenhum)',
        'prepares_emulados' => ($conexao['options'][PDO::ATTR_EMULATE_PREPARES] ?? false) ? 'sim' : 'nao',
    ];

    // o teste que importa: a lambda fala com o banco?
    try {
        DB::select('select 1');
        $banco = 'CONECTOU';
    } catch (Throwable $e) {
        $banco = 'FALHOU: '.categoriaDoErro($e);
    }

    // a chave de criptografia funciona? é o que o middleware web exige
    try {
        encrypt('teste');
        $cripto = 'OK';
    } catch (Throwable $e) {
        $cripto = 'FALHOU: '.class_basename($e);
    }

    $escrita = is_writable(storage_path('framework/views')) ? 'OK' : 'SEM PERMISSAO';

    return response()->json([
        'variaveis' => $variaveis,
        'conexao_resolvida' => $resolvido,
        'banco' => $banco,
        'criptografia' => $cripto,
        'storage' => $escrita.' ('.storage_path().')',
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

/**
 * Reduz a exceção a uma categoria conhecida. A mensagem crua do PDO carrega
 * host e usuário, então ela nunca sai daqui.
 */
function categoriaDoErro(Throwable $e): string
{
    $mensagem = strtolower($e->getMessage());

    $categorias = [
        'could not translate host name' => 'host nao resolve (DNS) — variavel de host ausente ou errada',
        'connection refused' => 'conexao recusada — provavelmente apontando para 127.0.0.1',
        'password authentication failed' => 'usuario ou senha invalidos',
        'no password supplied' => 'senha ausente',
        'timeout' => 'tempo esgotado ao conectar',
        'timed out' => 'tempo esgotado ao conectar',
        'ssl' => 'problema de SSL — falta sslmode=require?',
        'does not exist' => 'banco ou role inexistente',
        'could not find driver' => 'driver pdo_pgsql ausente no runtime',
    ];

    foreach ($categorias as $trecho => $explicacao) {
        if (str_contains($mensagem, $trecho)) {
            return $explicacao;
        }
    }

    return class_basename($e).' (codigo '.($e->getCode() ?: 'sem codigo').')';
}
