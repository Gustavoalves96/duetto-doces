<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use App\Support\PaletaGraficos;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Quanto cada sabor trouxe de receita no período. */
class FaturamentoPorProduto extends ChartWidget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Faturamento por produto';

    protected ?string $description = 'Quanto cada sabor vendeu, antes de desconto e taxa.';

    protected ?string $maxHeight = '280px';

    protected ?string $emptyStateHeading = 'Nenhuma venda no período';

    protected function getData(): array
    {
        $fatias = [];
        $outros = '0';

        foreach ($this->relatorio()->margemPorProduto($this->inicio(), $this->fim()) as $linha) {
            if (! Decimal::positivo($linha['receita'])) {
                continue;
            }

            $cor = PaletaGraficos::produto($linha['produto_id']);

            if ($cor === null) {
                $outros = Decimal::soma($outros, $linha['receita']);

                continue;
            }

            $fatias[] = [$linha['nome'], $linha['receita'], $cor];
        }

        // maior fatia primeiro, que é como se lê uma pizza
        usort($fatias, fn (array $a, array $b) => Decimal::compara($b[1], $a[1]));

        if (Decimal::positivo($outros)) {
            $fatias[] = ['Outros', $outros, PaletaGraficos::NEUTRO];
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

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getOptions(): RawJs
    {
        return $this->opcoesDePizza();
    }
}
