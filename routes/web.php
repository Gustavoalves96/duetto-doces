<?php

use Illuminate\Support\Facades\Route;

/*
 * O sistema é só o painel. Não existe site público, então a raiz manda direto
 * para o /admin — a welcome page do Laravel depende de um build do Vite que
 * este projeto não usa e daria 500 em produção.
 *
 * Route::redirect e não uma closure. O route:cache acabou saindo do build (ele
 * serializa closures gravando o caminho absoluto da máquina de build, que não
 * existe no runtime da lambda), mas a forma sem closure é melhor de qualquer
 * jeito: é mais direta e não depende de serialização.
 */
Route::redirect('/', '/admin');
