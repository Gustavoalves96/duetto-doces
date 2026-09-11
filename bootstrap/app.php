<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A Vercel termina o TLS num proxy e repassa a requisição em http.
        // Sem confiar no proxy, o Laravel monta as URLs de asset como http://
        // e o painel quebra inteiro com mixed content no navegador.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * TEMPORÁRIO — remover quando o deploy estabilizar.
         *
         * Numa lambda não dá para abrir o log, e ligar APP_DEBUG exporia o
         * stack trace num site público. Então a última exceção é gravada no
         * próprio banco, de onde dá para lê-la com segurança.
         *
         * Guarda só classe, arquivo, linha e mensagem — sem payload de
         * requisição, sem variável de ambiente.
         */
        $exceptions->report(function (Throwable $e): void {
            try {
                DB::table('cache')->updateOrInsert(
                    ['key' => 'diagnostico:ultima_excecao'],
                    [
                        'value' => json_encode([
                            'quando' => now()->toDateTimeString(),
                            'url' => request()?->fullUrl(),
                            'classe' => $e::class,
                            'mensagem' => mb_substr($e->getMessage(), 0, 500),
                            'arquivo' => str_replace(base_path(), '', $e->getFile()).':'.$e->getLine(),
                            'origem' => collect($e->getTrace())
                                ->take(6)
                                ->map(fn ($f) => str_replace(base_path(), '', $f['file'] ?? '?').':'.($f['line'] ?? '?'))
                                ->implode(' <- '),
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'expiration' => time() + 86400,
                    ],
                );
            } catch (Throwable) {
                // diagnóstico nunca pode derrubar o tratamento do erro real
            }
        });
    })->create();
