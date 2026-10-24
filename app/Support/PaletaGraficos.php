<?php

namespace App\Support;

use App\Enums\CategoriaDespesa;
use App\Models\Produto;

/**
 * Cores dos gráficos de relatório.
 *
 * A cor segue a coisa, não a posição: o Brownie é sempre da mesma cor, em
 * qualquer período, mesmo que num mês ele venda mais e no outro menos. Se a
 * cor seguisse o ranking, quem compara dois meses leria o gráfico errado.
 */
class PaletaGraficos
{
    /** Ordem fixa, validada para daltonismo nos pares vizinhos. */
    public const CORES = [
        '#2a78d6', // azul
        '#eb6834', // laranja
        '#1baf7a', // verde-água
        '#eda100', // amarelo
        '#e87ba4', // rosa
        '#008300', // verde
        '#6250d6', // violeta
        '#e34948', // vermelho
    ];

    /** "Outros": tudo que não cabe numa cor própria. */
    public const NEUTRO = '#a3a3a3';

    // destino do faturamento: CMV, taxa, despesa e o que sobra
    public const CMV = '#2a78d6';

    public const TAXAS = '#eb6834';

    public const DESPESAS = '#6250d6';

    public const LUCRO = '#1baf7a';

    /**
     * Cor fixa por produto, pela ordem de cadastro. Do 8º em diante o produto
     * cai em "Outros" — mais cores que isso ninguém distingue numa pizza.
     */
    public static function produto(int $produtoId): ?string
    {
        $cores = once(fn () => Produto::query()
            ->orderBy('id')
            ->pluck('id')
            ->take(count(self::CORES) - 1)
            ->values()
            ->mapWithKeys(fn (int $id, int $posicao) => [$id => self::CORES[$posicao]])
            ->all());

        return $cores[$produtoId] ?? null;
    }

    /** As categorias que aparecem de verdade ganham cor; o resto vira "Outras". */
    public static function categoria(CategoriaDespesa $categoria): ?string
    {
        return match ($categoria) {
            CategoriaDespesa::ProLabore => self::CORES[0],
            CategoriaDespesa::Embalagem => self::CORES[1],
            CategoriaDespesa::Aluguel => self::CORES[2],
            CategoriaDespesa::Energia => self::CORES[3],
            CategoriaDespesa::Gas => self::CORES[4],
            CategoriaDespesa::Transporte => self::CORES[5],
            CategoriaDespesa::Marketing => self::CORES[6],
            CategoriaDespesa::Impostos => self::CORES[7],
            default => null,
        };
    }
}
