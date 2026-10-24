{{--
    Correção de um bug do Filament 5: em tabela com ->stackedOnMobile(), a
    partir de 640px uma regra força TODO cabeçalho a aparecer
    (thead > tr > .fi-ta-header-cell { display: table-cell }), por cima do
    ->visibleFrom()/->hiddenFrom() da coluna. As células obedecem e o
    cabeçalho não, então a tabela desalinha e passa da largura da tela.

    Aqui as mesmas regras de visibilidade voltam a valer no cabeçalho, com um
    seletor mais específico que o do Filament. Larguras = breakpoints dele.
--}}
@php
    $breakpoints = ['sm' => '40rem', 'md' => '48rem', 'lg' => '64rem', 'xl' => '80rem', '2xl' => '96rem'];
    $cabecalho = '.fi-ta-table.fi-ta-table-stacked-on-mobile>thead>tr>.fi-ta-header-cell';
    // classe CSS com o nome do breakpoint escapado, como o Filament gera
    $classe = fn (string $bp, string $sufixo) => ($bp === '2xl' ? '\\32 xl' : $bp).'\\:fi-'.$sufixo;
@endphp
<style>
    @foreach ($breakpoints as $bp => $largura)
        {!! $cabecalho !!}.{!! $classe($bp, 'visible') !!} { display: none; }
        @media (min-width: {!! $largura !!}) {
            {!! $cabecalho !!}.{!! $classe($bp, 'visible') !!} { display: table-cell; }
            {!! $cabecalho !!}.{!! $classe($bp, 'hidden') !!} { display: none; }
        }
    @endforeach
</style>
