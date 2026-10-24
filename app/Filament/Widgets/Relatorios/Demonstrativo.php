<?php

namespace App\Filament\Widgets\Relatorios;

use App\Support\Decimal;
use Filament\Widgets\Widget;

/**
 * A conta inteira, linha por linha, na ordem em que o dinheiro some.
 * Os gráficos mostram a proporção; aqui ficam os centavos.
 */
class Demonstrativo extends Widget
{
    use RecebePeriodo;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.relatorios.demonstrativo';

    protected function getViewData(): array
    {
        $resumo = $this->resumo();

        return [
            'linhas' => [
                ['Faturamento bruto', $resumo['faturamento_bruto'], 'Soma dos itens, já com desconto', null],
                ['(−) Maquininha e delivery', $resumo['taxas'], 'O que a operadora fica', 'saida'],
                ['(=) Faturamento líquido', $resumo['faturamento_liquido'], 'O que realmente entrou na conta', 'subtotal'],
                ['(−) CMV', $resumo['cmv'], 'Custo congelado dos produtos vendidos', 'saida'],
                ['(=) Margem de contribuição', $resumo['margem_contribuicao'], 'Sobra para pagar a operação', 'subtotal'],
                ['(−) Despesas', $resumo['despesas'], 'Aluguel, energia, embalagem, pró-labore', 'saida'],
            ],
            'lucro' => $resumo['lucro_liquido'],
            'prejuizo' => Decimal::negativo($resumo['lucro_liquido']),
            'margemPercentual' => Decimal::paraBr($resumo['margem_percentual'], 1),
        ];
    }
}
