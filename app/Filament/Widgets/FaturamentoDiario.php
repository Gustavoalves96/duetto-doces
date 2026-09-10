<?php

namespace App\Filament\Widgets;

use App\Services\RelatorioFinanceiro;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class FaturamentoDiario extends ChartWidget
{
    protected ?string $heading = 'Faturamento por dia';

    protected ?string $description = 'Valor cobrado do cliente, já com desconto. Orçamento e cancelado ficam de fora.';

    protected int|string|array $columnSpan = 'full';

    // fecha o dashboard, depois dos números e das encomendas
    protected static ?int $sort = 3;

    // sem isso o Chart.js estica o gráfico e ele ocupa meia tela no desktop
    protected ?string $maxHeight = '240px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Últimos 7 dias',
            '30' => 'Últimos 30 dias',
            '90' => 'Últimos 90 dias',
        ];
    }

    protected function getData(): array
    {
        $dias = (int) ($this->filter ?? 30);

        $serie = app(RelatorioFinanceiro::class)->faturamentoDiario(
            now()->subDays($dias - 1)->startOfDay(),
            now()->endOfDay(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Faturamento (R$)',
                    'data' => array_map(fn (string $valor) => (float) $valor, array_values($serie)),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(
                fn (string $data) => Carbon::parse($data)->format('d/m'),
                array_keys($serie),
            ),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
