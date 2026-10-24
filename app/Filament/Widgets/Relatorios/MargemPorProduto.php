<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use App\Support\PaletaGraficos;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Qual sabor sustenta o negócio. Barra e não pizza: margem pode ser
 * negativa, e pizza não tem como mostrar um produto que dá prejuízo.
 */
class MargemPorProduto extends ChartWidget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Margem por produto';

    protected ?string $description = 'O que sobra de cada sabor depois do custo e da taxa da maquininha (rateada).';

    protected ?string $maxHeight = '280px';

    protected ?string $emptyStateHeading = 'Nenhuma venda no período';

    protected function getData(): array
    {
        $linhas = $this->relatorio()->margemPorProduto($this->inicio(), $this->fim());

        if ($linhas === []) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Margem',
                'data' => array_map(fn (array $linha) => (float) $linha['margem'], $linhas),
                'backgroundColor' => array_map(
                    fn (array $linha) => PaletaGraficos::produto($linha['produto_id']) ?? PaletaGraficos::NEUTRO,
                    $linhas,
                ),
                'borderRadius' => 4,
                'maxBarThickness' => 36,
                // o tooltip mostra o resto da conta sem precisar de outra tabela
                'detalhes' => array_map(fn (array $linha) => Decimal::paraBr($linha['margem_percentual'], 1).'% · '
                    .Decimal::paraReal($linha['margem_unitaria']).'/un · '
                    .Decimal::paraBr($linha['quantidade'], 0).' un vendidas', $linhas),
            ]],
            'labels' => array_column($linhas, 'nome'),
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
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (contexto) => ' ' + contexto.parsed.x.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }),
                            afterLabel: (contexto) => ' ' + (contexto.dataset.detalhes?.[contexto.dataIndex] ?? ''),
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            callback: (valor) => valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }),
                        },
                        grid: { display: true },
                    },
                },
            }
        JS);
    }
}
