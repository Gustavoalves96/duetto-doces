<?php

namespace App\Filament\Forms\Components;

use App\Support\Decimal;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Campo de dinheiro no padrão brasileiro: R$ 1.234,56.
 *
 * A máscara é do lado do cliente (plugin mask do Alpine, que vem no Livewire).
 * A conversão para o formato do banco acontece na desidratação, então o que
 * chega no Eloquent é sempre "1234.56" — nunca a string com vírgula.
 */
class CampoDinheiro extends TextInput
{
    protected string|Closure|null $valorMinimo = null;

    protected string|Closure|null $valorMaximo = null;

    /** Casas decimais. 2 para preço e total, 4 para custo por grama. */
    protected int $casas = Decimal::DINHEIRO;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->prefix('R$')
            ->placeholder('0,00')
            ->inputMode('decimal')
            ->extraInputAttributes(['class' => 'text-right'])
            // $money(input, separadorDecimal, separadorMilhar, casas)
            // As aspas do JS têm que ser SIMPLES: o Filament imprime isso dentro
            // de x-mask:dynamic="...", e aspas duplas aqui fechariam o atributo
            // no meio, quebrando a máscara sem dar erro nenhum.
            ->mask(fn (CampoDinheiro $component) => RawJs::make(
                '$money($input, \',\', \'.\', '.$component->getCasas().')'
            ))
            ->formatStateUsing(function (mixed $state, CampoDinheiro $component): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return Decimal::paraBr($state, $component->getCasas());
            })
            ->dehydrateStateUsing(function (mixed $state, CampoDinheiro $component): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return Decimal::arredonda(Decimal::deBr($state), $component->getCasas());
            })
            ->rules([
                fn (CampoDinheiro $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! preg_match('/^-?[\d.]*,?\d*$/', (string) $value)) {
                        $fail('Informe um valor válido, como 1.234,56.');

                        return;
                    }

                    $numero = Decimal::deBr($value);

                    $minimo = $component->getValorMinimo();
                    if ($minimo !== null && Decimal::menor($numero, $minimo)) {
                        $fail('O valor não pode ser menor que '.Decimal::paraReal($minimo).'.');
                    }

                    $maximo = $component->getValorMaximo();
                    if ($maximo !== null && Decimal::maior($numero, $maximo)) {
                        $fail('O valor não pode ser maior que '.Decimal::paraReal($maximo).'.');
                    }
                },
            ]);
    }

    /** Troca a precisão. Use 4 para custo unitário de insumo. */
    public function casas(int $casas): static
    {
        $this->casas = $casas;

        return $this;
    }

    public function getCasas(): int
    {
        return $this->casas;
    }

    /** Impede valor negativo — o caso mais comum. */
    public function naoNegativo(): static
    {
        return $this->minimo('0');
    }

    /** Exige valor maior que zero, para campos onde zero não faz sentido. */
    public function maiorQueZero(): static
    {
        $this->valorMinimo = '0.01';

        return $this;
    }

    public function minimo(string|Closure|null $valor): static
    {
        $this->valorMinimo = $valor;

        return $this;
    }

    public function maximo(string|Closure|null $valor): static
    {
        $this->valorMaximo = $valor;

        return $this;
    }

    public function getValorMinimo(): ?string
    {
        $valor = $this->evaluate($this->valorMinimo);

        return $valor === null ? null : (string) $valor;
    }

    public function getValorMaximo(): ?string
    {
        $valor = $this->evaluate($this->valorMaximo);

        return $valor === null ? null : (string) $valor;
    }
}
