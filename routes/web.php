<?php

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

/**
 * O sistema é só o painel. Não existe site público, então a raiz manda direto
 * para o /admin — a welcome page do Laravel depende de um build do Vite que
 * este projeto não usa e daria 500 em produção.
 */
Route::get('/', fn () => Redirect::to('/admin'));
