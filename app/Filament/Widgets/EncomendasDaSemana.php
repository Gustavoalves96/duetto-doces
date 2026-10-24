<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Pedidos\Actions\AcoesDeEncomenda;
use App\Filament\Resources\Pedidos\Tables\PedidosTable;
use App\Models\Pedido;
use App\Support\Decimal;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * O que precisa sair do forno nos próximos dias.
 * É a tela que a Aninha olha de manhã.
 */
class EncomendasDaSemana extends TableWidget
{
    protected static ?string $heading = 'Encomendas a entregar';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->query(fn (): Builder => Pedido::query()
                ->pendentes()
                ->with(['cliente', 'itens.produto'])
                ->orderBy('data_entrega'))
            ->defaultPaginationPageOption(5)
            // no celular vira cartão e mostra tudo; na tabela, o número do
            // pedido e o total só aparecem quando a tela tem espaço
            ->columns([
                TextColumn::make('id')
                    ->label('Pedido')
                    ->formatStateUsing(fn (Pedido $record) => $record->identificacao)
                    ->visibleFrom('lg'),

                TextColumn::make('cliente.nome')
                    ->label('Cliente')
                    ->default('—')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('data_entrega')
                    ->label('Entrega')
                    ->date('d/m')
                    ->description(fn (Pedido $record) => $record->data_entrega?->diffForHumans())
                    ->color(fn (Pedido $record) => $record->data_entrega?->isPast() ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('itens')
                    ->label('O que fazer')
                    ->wrap()
                    ->state(fn (Pedido $record) => $record->itens
                        ->map(fn ($item) => Decimal::paraBr($item->quantidade, 0).'x '.($item->produto?->nome ?? '?'))
                        ->implode(', ')),

                PedidosTable::colunaStatus(),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->visibleFrom('xl')
                    ->state(fn (Pedido $record) => $record->total())
                    ->money('BRL', locale: 'pt_BR'),
            ])
            ->recordActions([
                AcoesDeEncomenda::entregar()->labeledFrom('xl'),

                ActionGroup::make([
                    AcoesDeEncomenda::alternarPagamento(),

                    AcoesDeEncomenda::alterarEntrega(),
                ]),
            ])
            ->emptyStateHeading('Nenhuma encomenda em aberto')
            ->emptyStateDescription('Tudo entregue por aqui.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
