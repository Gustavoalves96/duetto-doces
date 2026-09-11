<?php

use Illuminate\Support\Facades\Route;

/*
 * O sistema é só o painel. Não existe site público, então a raiz manda direto
 * para o /admin — a welcome page do Laravel depende de um build do Vite que
 * este projeto não usa e daria 500 em produção.
 *
 * Route::redirect e não uma closure: closure não sobrevive ao route:cache, que
 * roda no build para o painel não remontar a tabela de rotas a cada requisição.
 */
Route::redirect('/', '/admin');
