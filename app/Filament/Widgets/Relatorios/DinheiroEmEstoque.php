<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use App\Support\PaletaGraficos;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dinheiro parado na prateleira, aos custos médios de hoje.
 * Não depende do período: é uma foto do agora.
 */
class DinheiroEmEstoque extends ChartWidget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Dinheiro parado em estoque';

    protected ?string $maxHeight = '280px';

    protected ?string $emptyStateHeading = 'Estoque vazio';

    public function getDescription(): ?string
    {
        return 'Hoje, aos custos médios atuais: '.Decimal::paraReal($this->estoque()['total']).' no total.';
    }

    protected function getData(): array
    {
        $estoque = $this->estoque();

        // estoque negativo existe (usaram antes de lançar a compra), mas não vira fatia
        $fatias = array_values(array_filter([
            ['Insumos', $estoque['insumos'], PaletaGraficos::CORES[0]],
            ['Produto pronto', $estoque['produtos'], PaletaGraficos::CORES[1]],
        ], fn (array $fatia) => Decimal::positivo($fatia[1])));

        if ($fatias === []) {
            return [];
        }

        return [
            'datasets' => [[
                'data' => array_map(fn (array $fatia) => (float) $fatia[1], $fatias),
                'backgroundColor' => array_column($fatias, 2),
            ]],
            'labels' => array_column($fatias, 0),
        ];
    }

    private function estoque(): array
    {
        return once(fn () => $this->relatorio()->valorEmEstoque());
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
