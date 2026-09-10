<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Telefone brasileiro, aceitando fixo e celular.
 * Guarda só os dígitos; a máscara é enfeite de tela.
 */
class CampoTelefone extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tel()
            ->placeholder('(11) 98765-4321')
            // 11 dígitos vira celular, 10 fica como fixo
            ->mask(RawJs::make(<<<'JS'
                $input.replace(/\D/g, '').length > 10
                    ? '(99) 99999-9999'
                    : '(99) 9999-9999'
            JS))
            ->formatStateUsing(function (?string $state): ?string {
                $digitos = preg_replace('/\D/', '', (string) $state);

                return match (strlen((string) $digitos)) {
                    11 => sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7)),
                    10 => sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6)),
                    default => $state,
                };
            })
            ->dehydrateStateUsing(function (?string $state): ?string {
                $digitos = preg_replace('/\D/', '', (string) $state);

                return $digitos === '' ? null : $digitos;
            })
            ->rule('regex:/^\(?\d{2}\)?[\s-]?\d{4,5}-?\d{4}$/')
            ->validationMessages([
                'regex' => 'Informe um telefone válido, com DDD.',
            ]);
    }
}
