<?php

namespace App\Filament\Pages;

use App\Services\RelatorioFinanceiro;
use App\Support\Decimal;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Fechamento do período. Tudo aqui sai do serviço de relatório, que soma em
 * decimal exato a partir do custo CONGELADO nos itens do pedido.
 */
class Relatorios extends Page
{
    protected string $view = 'filament.pages.relatorios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Relatórios';

    protected static ?string $navigationLabel = 'Relatórios';

    #[Url]
    public ?string $de = null;

    #[Url]
    public ?string $ate = null;

    #[Url]
    public string $atalho = 'mes_atual';

    public function mount(): void
    {
        $this->aplicarAtalho($this->atalho);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->statePath('')
            ->components([
                Select::make('atalho')
                    ->label('Período')
                    ->native(false)
                    ->live()
                    ->options([
                        'mes_atual' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'ultimos_30' => 'Últimos 30 dias',
                        'ultimos_90' => 'Últimos 90 dias',
                        'ano' => 'Este ano',
                        'personalizado' => 'Escolher datas',
                    ])
                    ->afterStateUpdated(fn (Set $set, $state) => $this->aplicarAtalho((string) $state)),

                DatePicker::make('de')
                    ->label('De')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn () => $this->atalho = 'personalizado'),

                DatePicker::make('ate')
                    ->label('Até')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn () => $this->atalho = 'personalizado'),
            ]);
    }

    public function aplicarAtalho(string $atalho): void
    {
        [$de, $ate] = match ($atalho) {
            'mes_passado' => [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'ultimos_30' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'ultimos_90' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
            'ano' => [now()->startOfYear(), now()->endOfYear()],
            'personalizado' => [
                Carbon::parse($this->de ?? now()->startOfMonth()),
                Carbon::parse($this->ate ?? now()),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $this->de = $de->toDateString();
        $this->ate = $ate->toDateString();
    }

    /** Tudo que a view precisa, calculado de uma vez só. */
    public function getDadosProperty(): array
    {
        $servico = app(RelatorioFinanceiro::class);
        $de = Carbon::parse($this->de ?? now()->startOfMonth());
        $ate = Carbon::parse($this->ate ?? now());

        return [
            'de' => $de,
            'ate' => $ate,
            'resumo' => $servico->resumo($de, $ate),
            'produtos' => $servico->margemPorProduto($de, $ate),
            'despesas' => $servico->despesasPorCategoria($de, $ate),
            'equilibrio' => $servico->pontoEquilibrio($de, $ate),
            'estoque' => $servico->valorEmEstoque(),
        ];
    }

    /** Helper para a view não precisar importar o Decimal. */
    public function real(mixed $valor): string
    {
        return Decimal::paraReal($valor);
    }

    public function numero(mixed $valor, int $casas = 2): string
    {
        return Decimal::paraBr($valor, $casas);
    }
}
