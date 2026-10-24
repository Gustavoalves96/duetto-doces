{{-- Só o filtro: os números e gráficos são os widgets do rodapé da página. --}}
<x-filament-panels::page>
    <x-filament::section
        compact
        :heading="'Resultado de '.\Illuminate\Support\Carbon::parse($this->de)->format('d/m/Y').' a '.\Illuminate\Support\Carbon::parse($this->ate)->format('d/m/Y')"
    >
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>
    </x-filament::section>
</x-filament-panels::page>
