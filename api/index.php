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

/*
 * Modo de manutenção sempre no driver de arquivo.
 *
 * O PreventRequestsDuringMaintenance é middleware GLOBAL: ele roda antes do
 * roteamento, em toda requisição. Com o driver 'cache' e o store 'database'
 * (o padrão de config/app.php), essa verificação abre uma conexão com o banco
 * antes de qualquer outra coisa — e se o banco estiver fora, a aplicação
 * inteira responde 500 sem sequer chegar na rota, inclusive num 404.
 *
 * O driver de arquivo só olha um arquivo em storage/framework, que aqui é
 * /tmp. Zero dependência externa no caminho crítico. Forçado aqui, e não no
 * vercel.json, para que nenhuma variável do painel possa sobrescrever.
 */
$_ENV['APP_MAINTENANCE_DRIVER'] = 'file';
$_SERVER['APP_MAINTENANCE_DRIVER'] = 'file';
putenv('APP_MAINTENANCE_DRIVER=file');

require __DIR__.'/../public/index.php';
