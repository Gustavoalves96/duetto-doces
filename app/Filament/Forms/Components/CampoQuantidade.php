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

                return Decimal::paraBr($state, Decimal::QUANTIDADE);
            })
            ->dehydrateStateUsing(function (mixed $state): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return Decimal::quantidade(Decimal::de($state));
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

                    $minimo = $component->getValorMinimo();

                    if ($minimo !== null && Decimal::menor(Decimal::de($value), $minimo)) {
                        $fail('A quantidade precisa ser de no mínimo '.Decimal::paraBr($minimo, 3).'.');
                    }
                },
            ]);
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
