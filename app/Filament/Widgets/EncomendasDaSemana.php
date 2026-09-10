<?php

namespace App\Filament\Widgets;

use App\Enums\StatusPedido;
use App\Models\Pedido;
use App\Services\FinalizarPedido;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
            ->query(fn (): Builder => Pedido::query()
                ->pendentes()
                ->with(['cliente', 'itens.produto'])
                ->orderBy('data_entrega'))
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('id')
                    ->label('Pedido')
                    ->formatStateUsing(fn (Pedido $record) => $record->identificacao),

                TextColumn::make('cliente.nome')
                    ->label('Cliente')
                    ->default('—')
                    ->searchable(),

                TextColumn::make('data_entrega')
                    ->label('Entrega')
                    ->date('d/m/Y')
                    ->description(fn (Pedido $record) => $record->data_entrega?->diffForHumans())
                    ->color(fn (Pedido $record) => $record->data_entrega?->isPast() ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('itens')
                    ->label('O que fazer')
                    ->wrap()
                    ->state(fn (Pedido $record) => $record->itens
                        ->map(fn ($item) => Decimal::paraBr($item->quantidade, 0).'x '.($item->produto?->nome ?? '?'))
                        ->implode(', ')),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->state(fn (Pedido $record) => $record->total())
                    ->money('BRL', locale: 'pt_BR'),
            ])
            ->recordActions([
                Action::make('avancar')
                    ->label(fn (Pedido $record) => $record->status->proximo()?->getLabel() ?? '—')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('success')
                    ->visible(fn (Pedido $record) => $record->status->proximo() !== null)
                    ->requiresConfirmation(fn (Pedido $record) => $record->status === StatusPedido::Orcamento)
                    ->action(function (Pedido $record, FinalizarPedido $servico) {
                        if ($proximo = $record->status->proximo()) {
                            $servico->mudarStatus($record, $proximo);

                            Notification::make()
                                ->title($record->identificacao.' → '.$proximo->getLabel())
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhuma encomenda em aberto')
            ->emptyStateDescription('Tudo entregue por aqui.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
