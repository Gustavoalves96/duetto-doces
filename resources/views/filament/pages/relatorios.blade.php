@php
    $dados = $this->dados;
    $resumo = $dados['resumo'];
    $equilibrio = $dados['equilibrio'];
    $lucroNegativo = str_starts_with($resumo['lucro_liquido'], '-');
@endphp

<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    <x-filament::section>
        <x-slot name="heading">
            Resultado de {{ $dados['de']->format('d/m/Y') }} a {{ $dados['ate']->format('d/m/Y') }}
        </x-slot>

        <x-slot name="description">
            {{ $resumo['pedidos'] }} {{ Str::plural('pedido', $resumo['pedidos']) }} ·
            {{ $this->numero($resumo['unidades_vendidas'], 0) }} unidades vendidas ·
            ticket médio {{ $this->real($resumo['ticket_medio']) }}
        </x-slot>

        {{-- a conta que interessa, na ordem em que o dinheiro some --}}
        <dl class="divide-y divide-gray-100 dark:divide-white/10 text-sm">
            @foreach ([
                ['Faturamento bruto', $resumo['faturamento_bruto'], 'Soma dos itens, já com desconto aplicado', null],
                ['(−) Taxas de maquininha', $resumo['taxas'], 'O que a operadora fica', 'text-danger-600'],
                ['(=) Faturamento líquido', $resumo['faturamento_liquido'], 'O que realmente entrou na conta', 'font-semibold'],
                ['(−) CMV', $resumo['cmv'], 'Custo congelado dos produtos vendidos', 'text-danger-600'],
                ['(=) Margem de contribuição', $resumo['margem_contribuicao'], 'Sobra para pagar a operação', 'font-semibold'],
                ['(−) Despesas', $resumo['despesas'], 'Aluguel, energia, embalagem, pró-labore', 'text-danger-600'],
            ] as [$rotulo, $valor, $ajuda, $classe])
                <div class="flex items-baseline justify-between gap-4 py-2">
                    <dt class="{{ $classe ?? '' }}">
                        {{ $rotulo }}
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $ajuda }}</span>
                    </dt>
                    <dd class="whitespace-nowrap tabular-nums {{ $classe ?? '' }}">{{ $this->real($valor) }}</dd>
                </div>
            @endforeach

            <div class="flex items-baseline justify-between gap-4 pt-3">
                <dt class="text-base font-bold">
                    Lucro líquido
                    <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">
                        Margem de {{ $this->numero($resumo['margem_percentual'], 1) }}% sobre o líquido
                    </span>
                </dt>
                <dd @class([
                    'whitespace-nowrap text-base font-bold tabular-nums',
                    'text-danger-600' => $lucroNegativo,
                    'text-success-600' => ! $lucroNegativo,
                ])>
                    {{ $this->real($resumo['lucro_liquido']) }}
                </dd>
            </div>
        </dl>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Margem por produto</x-slot>
            <x-slot name="description">Qual sabor sustenta o negócio. A taxa da maquininha é rateada por faturamento.</x-slot>

            @forelse ($dados['produtos'] as $produto)
                @php $ruim = str_starts_with($produto['margem'], '-'); @endphp
                <div class="flex items-baseline justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/10">
                    <div>
                        <span class="font-medium">{{ $produto['nome'] }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->numero($produto['quantidade'], 0) }} un ·
                            receita {{ $this->real($produto['receita']) }} ·
                            custo {{ $this->real($produto['custo']) }}
                        </span>
                    </div>
                    <div class="text-right whitespace-nowrap">
                        <span @class([
                            'font-semibold tabular-nums',
                            'text-danger-600' => $ruim,
                            'text-success-600' => ! $ruim,
                        ])>{{ $this->real($produto['margem']) }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->numero($produto['margem_percentual'], 1) }}% ·
                            {{ $this->real($produto['margem_unitaria']) }}/un
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma venda no período.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Despesas por categoria</x-slot>
            <x-slot name="description">Para onde o dinheiro da operação foi.</x-slot>

            @forelse ($dados['despesas'] as $despesa)
                <div class="flex items-baseline justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/10">
                    <span>{{ $despesa['categoria']->getLabel() }}</span>
                    <span class="whitespace-nowrap tabular-nums">{{ $this->real($despesa['valor']) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Nenhuma despesa lançada. Se o aluguel e o pró-labore de vocês não estão aqui,
                    o lucro acima está otimista.
                </p>
            @endforelse
        </x-filament::section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Ponto de equilíbrio</x-slot>
            <x-slot name="description">Quantas unidades pagam a operação do período.</x-slot>

            @if ($equilibrio['unidades'] === '0')
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Sem vendas ou sem despesas no período, não dá para calcular.
                </p>
            @else
                <p class="text-3xl font-bold tabular-nums">{{ $this->numero($equilibrio['unidades'], 0) }} unidades</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    ≈ {{ $this->real($equilibrio['faturamento']) }} de faturamento.
                    Cada unidade contribui com {{ $this->real($equilibrio['margem_unitaria_media']) }}
                    e as despesas somam {{ $this->real($equilibrio['despesas']) }}.
                </p>
                <p class="mt-2 text-sm">
                    Vocês venderam <strong>{{ $this->numero($resumo['unidades_vendidas'], 0) }}</strong>.
                    @if ((float) $resumo['unidades_vendidas'] >= (float) $equilibrio['unidades'])
                        <span class="text-success-600 font-medium">Passou do ponto de equilíbrio.</span>
                    @else
                        <span class="text-danger-600 font-medium">Faltou para pagar a operação.</span>
                    @endif
                </p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Dinheiro parado em estoque</x-slot>
            <x-slot name="description">Vale hoje, aos custos médios atuais.</x-slot>

            <dl class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                <div class="flex justify-between py-2">
                    <dt>Insumos</dt>
                    <dd class="tabular-nums">{{ $this->real($dados['estoque']['insumos']) }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt>Produto acabado</dt>
                    <dd class="tabular-nums">{{ $this->real($dados['estoque']['produtos']) }}</dd>
                </div>
                <div class="flex justify-between pt-2 font-semibold">
                    <dt>Total</dt>
                    <dd class="tabular-nums">{{ $this->real($dados['estoque']['total']) }}</dd>
                </div>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
