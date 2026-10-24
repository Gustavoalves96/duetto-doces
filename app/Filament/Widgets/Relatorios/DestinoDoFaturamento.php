<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use App\Support\PaletaGraficos;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Cada real cobrado do cliente, repartido: produto (CMV), maquininha,
 * despesas e o que sobrou. As quatro fatias somam o faturamento bruto.
 */
class DestinoDoFaturamento extends ChartWidget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Para onde foi o faturamento';

    protected ?string $maxHeight = '280px';

    protected ?string $emptyStateHeading = 'Nenhuma venda no período';

    public function getDescription(): ?string
    {
        $resumo = $this->resumo();

        if (Decimal::negativo($resumo['lucro_liquido'])) {
            return 'Os custos passaram do faturamento: prejuízo de '
                .Decimal::paraReal(Decimal::mul($resumo['lucro_liquido'], '-1')).'.';
        }

        return 'De '.Decimal::paraReal($resumo['faturamento_bruto']).' cobrados, quanto foi para cada lado.';
    }

    protected function getData(): array
    {
        $resumo = $this->resumo();

        if (! Decimal::positivo($resumo['faturamento_bruto'])) {
            return [];
        }

        $fatias = [
            ['Produto vendido (CMV)', $resumo['cmv'], PaletaGraficos::CMV],
            ['Maquininha e delivery', $resumo['taxas'], PaletaGraficos::TAXAS],
            ['Despesas', $resumo['despesas'], PaletaGraficos::DESPESAS],
            // prejuízo não vira fatia: pizza não tem pedaço negativo
            ['Lucro', $resumo['lucro_liquido'], PaletaGraficos::LUCRO],
        ];

        $fatias = array_values(array_filter($fatias, fn (array $fatia) => Decimal::positivo($fatia[1])));

        return [
            'datasets' => [[
                'data' => array_map(fn (array $fatia) => (float) $fatia[1], $fatias),
                'backgroundColor' => array_column($fatias, 2),
            ]],
            'labels' => array_column($fatias, 0),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): RawJs
    {
        return $this->opcoesDePizza();
    }
}
