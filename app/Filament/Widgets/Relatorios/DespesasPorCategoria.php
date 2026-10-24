<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use App\Support\PaletaGraficos;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Para onde foi o dinheiro da operação. */
class DespesasPorCategoria extends ChartWidget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Despesas por categoria';

    protected ?string $maxHeight = '280px';

    protected ?string $emptyStateHeading = 'Nenhuma despesa lançada';

    protected ?string $emptyStateDescription = 'Se o aluguel e o pró-labore de vocês não estão em Despesas, o lucro está otimista.';

    public function getDescription(): ?string
    {
        $total = Decimal::somaLista(array_column($this->linhas(), 'valor'));

        return Decimal::positivo($total) ? 'Total de '.Decimal::paraReal($total).' no período.' : null;
    }

    protected function getData(): array
    {
        $fatias = [];
        $outras = '0';

        // já vem do maior para o menor
        foreach ($this->linhas() as $linha) {
            $cor = PaletaGraficos::categoria($linha['categoria']);

            if ($cor === null) {
                $outras = Decimal::soma($outras, $linha['valor']);

                continue;
            }

            $fatias[] = [$linha['categoria']->getLabel(), $linha['valor'], $cor];
        }

        if (Decimal::positivo($outras)) {
            $fatias[] = ['Outras', $outras, PaletaGraficos::NEUTRO];
        }

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

    private function linhas(): array
    {
        return once(fn () => $this->relatorio()->despesasPorCategoria($this->inicio(), $this->fim()));
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
