<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Os quatro números que respondem "o mês foi bom?". */
class ResumoDoPeriodo extends StatsOverviewWidget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $resumo = $this->resumo();
        $equilibrio = $this->relatorio()->pontoEquilibrio($this->inicio(), $this->fim());
        $prejuizo = Decimal::negativo($resumo['lucro_liquido']);

        return [
            Stat::make('Faturamento bruto', Decimal::paraReal($resumo['faturamento_bruto']))
                ->description($resumo['pedidos'].' '.str('pedido')->plural($resumo['pedidos'])
                    .' · ticket médio '.Decimal::paraReal($resumo['ticket_medio']))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('gray'),

            Stat::make('Faturamento líquido', Decimal::paraReal($resumo['faturamento_liquido']))
                ->description(Decimal::paraReal($resumo['taxas']).' ficaram com maquininha e delivery')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('gray'),

            Stat::make($prejuizo ? 'Prejuízo' : 'Lucro líquido', Decimal::paraReal($resumo['lucro_liquido']))
                ->description('Margem de '.Decimal::paraBr($resumo['margem_percentual'], 1).'% sobre o líquido')
                ->descriptionIcon($prejuizo ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($prejuizo ? 'danger' : 'success'),

            $this->pontoDeEquilibrio($resumo['unidades_vendidas'], $equilibrio),
        ];
    }

    /** @param array{unidades: string, faturamento: string} $equilibrio */
    private function pontoDeEquilibrio(string $vendidas, array $equilibrio): Stat
    {
        if ($equilibrio['unidades'] === '0') {
            return Stat::make('Ponto de equilíbrio', '—')
                ->description('Precisa de vendas e despesas no período')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('gray');
        }

        $passou = ! Decimal::menor($vendidas, $equilibrio['unidades']);

        return Stat::make('Ponto de equilíbrio', Decimal::paraBr($equilibrio['unidades'], 0).' un')
            ->description('Venderam '.Decimal::paraBr($vendidas, 0).' — '
                .($passou ? 'operação paga' : 'faltou para pagar a operação'))
            ->descriptionIcon($passou ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
            ->color($passou ? 'success' : 'danger');
    }
}
