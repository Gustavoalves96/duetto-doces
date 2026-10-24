<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Faturamento ao longo do período escolhido. Até três meses mostra dia a
 * dia; mais que isso agrupa por mês, senão viram 365 barrinhas ilegíveis.
 */
class FaturamentoNoPeriodo extends ChartWidget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Faturamento no período';

    protected ?string $description = 'Valor cobrado do cliente, já com desconto.';

    protected ?string $maxHeight = '240px';

    protected function getData(): array
    {
        $serie = $this->relatorio()->faturamentoDiario($this->inicio(), $this->fim());
        $porMes = count($serie) > 92;

        if ($porMes) {
            $agrupada = [];

            foreach ($serie as $dia => $valor) {
                $mes = substr($dia, 0, 7);
                $agrupada[$mes] = Decimal::dinheiro(Decimal::soma($agrupada[$mes] ?? '0', $valor));
            }

            $serie = $agrupada;
        }

        return [
            'datasets' => [[
                'label' => 'Faturamento',
                'data' => array_map(fn (string $valor) => (float) $valor, array_values($serie)),
                // mesma cor do gráfico do dashboard
                'backgroundColor' => '#f59e0b',
                'borderRadius' => 4,
                'maxBarThickness' => 32,
            ]],
            'labels' => array_map(
                fn (string $chave) => $porMes
                    ? Carbon::parse($chave.'-01')->translatedFormat('M/y')
                    : Carbon::parse($chave)->format('d/m'),
                array_keys($serie),
            ),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (contexto) => ' ' + contexto.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: true },
                        ticks: {
                            callback: (valor) => valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }),
                        },
                    },
                },
            }
        JS);
    }
}
