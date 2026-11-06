<?php

namespace App\Providers;

use Filament\Actions\Action;
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

        /*
         * Bug do Filament 5: com ->labeledFrom(), o aria-label nulo do botão
         * com texto vence o rótulo na versão só com ícone, e o botão fica sem
         * nome para leitor de tela. Um valor explícito vence esse merge.
         */
        Action::configureUsing(fn (Action $action) => $action->extraAttributes(
            fn (Action $action): array => $action->hasLabeledFromBreakpoint()
                ? ['aria-label' => e(strip_tags((string) $action->getLabel()))]
                : [],
            merge: true,
        ));
    }
}
