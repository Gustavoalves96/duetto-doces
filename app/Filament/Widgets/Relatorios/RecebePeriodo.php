<?php

namespace App\Filament\Widgets\Relatorios;

use App\Services\RelatorioFinanceiro;
use Filament\Support\RawJs;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

/**
 * Widgets da página de Relatórios: recebem o período da página e se
 * redesenham sozinhos quando o filtro muda.
 *
 * Ficam fora do dashboard ($isDiscovered = false em cada um) — sem período
 * escolhido eles não têm o que mostrar.
 */
trait RecebePeriodo
{
    #[Reactive]
    public ?string $de = null;

    #[Reactive]
    public ?string $ate = null;

    protected function inicio(): Carbon
    {
        return Carbon::parse($this->de ?? now()->startOfMonth());
    }

    protected function fim(): Carbon
    {
        return Carbon::parse($this->ate ?? now());
    }

    protected function relatorio(): RelatorioFinanceiro
    {
        return app(RelatorioFinanceiro::class);
    }

    /** O resumo é pedido por mais de um método do mesmo widget; calcula uma vez. */
    protected function resumo(): array
    {
        return once(fn () => $this->relatorio()->resumo($this->inicio(), $this->fim()));
    }

    /** Opções comuns das pizzas: legenda embaixo e valor em reais + % no tooltip. */
    protected function opcoesDePizza(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } },
                    tooltip: {
                        callbacks: {
                            label: (contexto) => {
                                const total = contexto.dataset.data.reduce((a, b) => a + b, 0)
                                const valor = contexto.parsed
                                const percentual = total > 0 ? (valor / total) * 100 : 0

                                return ' ' + valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                                    + ' (' + percentual.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%)'
                            },
                        },
                    },
                },
            }
        JS);
    }
}
