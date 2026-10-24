{{--
    O painel usa o CSS pronto do Filament, que não traz utilitários do Tailwind
    (text-3xl, divide-y...). Por isso o estilo desta tabela mora aqui mesmo.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        heading="Demonstrativo do período"
        description="A conta completa, do faturamento até o lucro."
    >
        <style>
            .dd-dre { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
            .dd-dre td { padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200); vertical-align: baseline; }
            .dark .dd-dre td { border-bottom-color: rgb(255 255 255 / 0.1); }
            .dd-dre td.dd-valor { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; padding-left: 1rem; }
            .dd-dre .dd-ajuda { display: block; font-size: 0.75rem; color: var(--gray-500); }
            .dd-dre .dd-saida .dd-valor { color: var(--danger-600); }
            .dark .dd-dre .dd-saida .dd-valor { color: var(--danger-400); }
            .dd-dre .dd-subtotal td { font-weight: 600; }
            .dd-dre .dd-final td { border-bottom: 0; padding-top: 0.75rem; font-size: 1rem; font-weight: 700; }
            .dd-dre .dd-final .dd-ajuda { font-weight: 400; }
            .dd-dre .dd-positivo .dd-valor { color: var(--success-600); }
            .dark .dd-dre .dd-positivo .dd-valor { color: var(--success-400); }
            .dd-dre .dd-negativo .dd-valor { color: var(--danger-600); }
            .dark .dd-dre .dd-negativo .dd-valor { color: var(--danger-400); }
        </style>

        <table class="dd-dre">
            <tbody>
                @foreach ($linhas as [$rotulo, $valor, $ajuda, $tipo])
                    <tr @class(['dd-saida' => $tipo === 'saida', 'dd-subtotal' => $tipo === 'subtotal'])>
                        <td>
                            {{ $rotulo }}
                            <span class="dd-ajuda">{{ $ajuda }}</span>
                        </td>
                        <td class="dd-valor">{{ \App\Support\Decimal::paraReal($valor) }}</td>
                    </tr>
                @endforeach

                <tr @class(['dd-final', 'dd-negativo' => $prejuizo, 'dd-positivo' => ! $prejuizo])>
                    <td>
                        {{ $prejuizo ? 'Prejuízo' : 'Lucro líquido' }}
                        <span class="dd-ajuda">Margem de {{ $margemPercentual }}% sobre o líquido</span>
                    </td>
                    <td class="dd-valor">{{ \App\Support\Decimal::paraReal($lucro) }}</td>
                </tr>
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
