<?php

namespace App\Filament\Forms\Components;

use App\Support\Decimal;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Quantidade com 3 casas decimais e vírgula: 1.250,500 g.
 *
 * Três casas porque a receita pede 0,5 de ovo ou 2,5 g de fermento, e porque
 * meia fornada divide tudo por dois.
 */
class CampoQuantidade extends TextInput
{
    protected string|Closure|null $valorMinimo = null;

    protected bool $inteiro = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->placeholder('0,000')
            ->inputMode('decimal')
            ->extraInputAttributes(['class' => 'text-right'])
            // aspas simples: aspas duplas fechariam o atributo x-mask no meio
            ->mask(RawJs::make('$money($input, \',\', \'.\', 3)'))
            ->formatStateUsing(function (mixed $state): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return Decimal::paraBr($state, $this->inteiro ? 0 : Decimal::QUANTIDADE);
            })
            ->dehydrateStateUsing(function (mixed $state): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return Decimal::quantidade(Decimal::deBr($state));
            })
            ->rules([
                fn (CampoQuantidade $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! preg_match('/^-?[\d.]*,?\d*$/', (string) $value)) {
                        $fail('Informe uma quantidade válida, como 1.250,500.');

                        return;
                    }

                    if ($component->ehInteiro() && ! Decimal::ehZero(Decimal::sub(
                        Decimal::deBr($value), bcadd(Decimal::deBr($value), '0', 0)
                    ))) {
                        $fail('Informe uma quantidade inteira, como 2.');

                        return;
                    }

                    $minimo = $component->getValorMinimo();

                    if ($minimo !== null && Decimal::menor(Decimal::deBr($value), $minimo)) {
                        $fail('A quantidade precisa ser de no mínimo '
                            .Decimal::paraBr($minimo, $component->ehInteiro() ? 0 : 3).'.');
                    }
                },
            ]);
    }

    /**
     * Só unidades inteiras: venda é por brownie, ninguém compra meio.
     * O banco continua com 3 casas; muda só o que a pessoa digita e vê.
     */
    public function inteiro(): static
    {
        $this->inteiro = true;

        return $this
            ->placeholder('0')
            ->inputMode('numeric')
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'));
    }

    public function ehInteiro(): bool
    {
        return $this->inteiro;
    }

    /** Quantidade tem que ser positiva em compra, receita e venda. */
    public function maiorQueZero(): static
    {
        $this->valorMinimo = '0.001';

        return $this;
    }

    public function naoNegativo(): static
    {
        $this->valorMinimo = '0';

        return $this;
    }

    public function minimo(string|Closure|null $valor): static
    {
        $this->valorMinimo = $valor;

        return $this;
    }

    public function getValorMinimo(): ?string
    {
        $valor = $this->evaluate($this->valorMinimo);

        return $valor === null ? null : (string) $valor;
    }
}
