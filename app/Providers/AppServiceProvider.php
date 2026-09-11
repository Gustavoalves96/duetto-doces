<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Em produção toda URL gerada sai como https.
         *
         * A Vercel termina o TLS num proxy e repassa a requisição em http para
         * a lambda. O trustProxies resolve a maior parte, mas o asset() ainda
         * conseguia montar link http — o favicon era bloqueado pelo navegador
         * como mixed content. Forçar o esquema fecha isso de uma vez, para
         * asset(), route() e redirect().
         */
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
