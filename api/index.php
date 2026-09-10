<?php

/**
 * Entrypoint das funções serverless da Vercel.
 *
 * A lambda tem o filesystem somente leitura fora de /tmp. Tudo que o Laravel
 * precisa escrever em runtime — views compiladas do Blade, cache de framework,
 * logs — é redirecionado para lá antes de a aplicação subir.
 *
 * Sessão e cache de aplicação NÃO ficam em /tmp de propósito: cada requisição
 * pode cair num container diferente, e sessão em arquivo derrubaria o login do
 * painel no request seguinte. Os dois vão para o Postgres do Neon, via
 * SESSION_DRIVER e CACHE_STORE definidos no vercel.json.
 */
$storage = '/tmp/storage';

foreach ([
    $storage.'/framework/views',
    $storage.'/framework/cache/data',
    $storage.'/framework/sessions',
    $storage.'/app/public',
    $storage.'/logs',
] as $diretorio) {
    if (! is_dir($diretorio)) {
        @mkdir($diretorio, 0755, true);
    }
}

// o Application::storagePath() do Laravel lê estas duas superglobais
$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;
putenv('LARAVEL_STORAGE_PATH='.$storage);

require __DIR__.'/../public/index.php';
