<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Insumos\InsumoResource;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Models\Insumo;
use App\Models\Pedido;
use App\Services\RelatorioFinanceiro;
use App\Support\Decimal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumoDoMes extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Este mês';

    protected ?string $description = 'Do dia 1 até hoje, comparado com o mês passado inteiro.';

    protected function getStats(): array
    {
        $relatorio = app(RelatorioFinanceiro::class);

        $mes = $relatorio->resumo(now()->startOfMonth(), now()->endOfMonth());
        $anterior = $relatorio->resumo(
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        );

        $pendentes = Pedido::query()->pendentes()->count();
        $atrasadas = Pedido::query()->pendentes()->whereDate('data_entrega', '<', now())->count();
        $faltando = Insumo::query()->ativos()->abaixoDoMinimo()->count();

        return [
            Stat::make('Faturamento líquido', Decimal::paraReal($mes['faturamento_liquido']))
                ->description($this->comparar($mes['faturamento_liquido'], $anterior['faturamento_liquido']))
                ->descriptionIcon($this->icone($mes['faturamento_liquido'], $anterior['faturamento_liquido']))
                ->color($this->cor($mes['faturamento_liquido'], $anterior['faturamento_liquido']))
                ->chart($this->serie()),

            Stat::make('Lucro líquido', Decimal::paraReal($mes['lucro_liquido']))
                // já descontou taxa, CMV e despesas: é o que sobra de verdade
                ->description('CMV '.Decimal::paraReal($mes['cmv']).' · despesas '.Decimal::paraReal($mes['despesas']))
                ->descriptionIcon(Decimal::negativo($mes['lucro_liquido'])
                    ? 'heroicon-m-arrow-trending-down'
                    : 'heroicon-m-arrow-trending-up')
                ->color(Decimal::negativo($mes['lucro_liquido']) ? 'danger' : 'success'),

            Stat::make('Encomendas em aberto', (string) $pendentes)
                ->description($atrasadas > 0
                    ? $atrasadas.' com entrega atrasada'
                    : 'Nenhuma atrasada')
                ->descriptionIcon($atrasadas > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($atrasadas > 0 ? 'danger' : 'success')
                ->url(PedidoResource::getUrl('index')),

            Stat::make('Insumos para comprar', (string) $faltando)
                ->description($faltando > 0
                    ? 'No limite ou abaixo do mínimo'
                    : 'Estoque tranquilo')
                ->descriptionIcon($faltando > 0 ? 'heroicon-m-shopping-cart' : 'heroicon-m-check-circle')
                ->color($faltando > 0 ? 'warning' : 'success')
                ->url(InsumoResource::getUrl('index')),
        ];
    }

    /** Sparkline dos últimos 14 dias para o card de faturamento. */
    private function serie(): array
    {
        $serie = app(RelatorioFinanceiro::class)->faturamentoDiario(
            now()->subDays(13)->startOfDay(),
            now()->endOfDay(),
        );

        return array_map(fn (string $valor) => (float) $valor, array_values($serie));
    }

    private function comparar(string $atual, string $anterior): string
    {
        if (! Decimal::positivo($anterior)) {
            return 'Mês passado sem faturamento';
        }

        $variacao = Decimal::mul(
            Decimal::div(Decimal::sub($atual, $anterior), $anterior),
            '100'
        );

        $sinal = Decimal::negativo($variacao) ? '' : '+';

        return $sinal.Decimal::paraBr($variacao, 1).'% vs. mês passado';
    }

    private function icone(string $atual, string $anterior): string
    {
        return Decimal::menor($atual, $anterior)
            ? 'heroicon-m-arrow-trending-down'
            : 'heroicon-m-arrow-trending-up';
    }

    private function cor(string $atual, string $anterior): string
    {
        return Decimal::menor($atual, $anterior) ? 'danger' : 'success';
    }
}
