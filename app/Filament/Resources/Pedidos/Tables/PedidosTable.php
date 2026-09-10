<?php

namespace App\Filament\Resources\Pedidos\Tables;

use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Models\Pedido;
use App\Services\FinalizarPedido;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PedidosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Pedido')
                    ->formatStateUsing(fn (Pedido $record) => $record->identificacao)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('cliente.nome')
                    ->label('Cliente')
                    ->searchable()
                    ->default('Balcão')
                    ->description(fn (Pedido $record) => $record->tipo->getLabel()),

                TextColumn::make('data_entrega')
                    ->label('Entrega')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    // encomenda atrasada e ainda aberta precisa saltar aos olhos
                    ->color(fn (Pedido $record) => $record->data_entrega
                        && $record->data_entrega->isPast()
                        && ! $record->status->finalizado()
                            ? 'danger'
                            : null),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->state(fn (Pedido $record) => $record->total())
                    ->money('BRL', locale: 'pt_BR')
                    ->description(fn (Pedido $record) => Decimal::positivo($record->taxa_operadora)
                        ? 'líquido '.Decimal::paraReal($record->faturamentoLiquido())
                        : null),

                TextColumn::make('margem')
                    ->label('Margem')
                    ->alignEnd()
                    ->state(fn (Pedido $record) => $record->jaBaixouEstoque()
                        ? Decimal::paraReal($record->margemContribuicao())
                        : '—')
                    ->color(fn (Pedido $record) => match (true) {
                        ! $record->jaBaixouEstoque() => 'gray',
                        Decimal::negativo($record->margemContribuicao()) => 'danger',
                        default => 'success',
                    })
                    ->toggleable(),

                TextColumn::make('saldo')
                    ->label('A receber')
                    ->alignEnd()
                    ->state(fn (Pedido $record) => Decimal::paraReal($record->saldoAReceber()))
                    ->color(fn (Pedido $record) => Decimal::positivo($record->saldoAReceber()) ? 'warning' : 'success')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusPedido::class)
                    ->multiple(),

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoPedido::class),

                Filter::make('pendentes')
                    ->label('Encomendas em aberto')
                    ->query(fn (Builder $query) => $query->pendentes())
                    ->toggle(),

                Filter::make('atrasadas')
                    ->label('Entrega atrasada')
                    ->query(fn (Builder $query) => $query
                        ->whereDate('data_entrega', '<', now())
                        ->whereNotIn('status', [StatusPedido::Entregue->value, StatusPedido::Cancelado->value]))
                    ->toggle(),

                QueryBuilder::make()
                    ->constraints([
                        DateConstraint::make('data')->label('Data do pedido'),
                        DateConstraint::make('data_entrega')->label('Data de entrega'),
                    ]),
            ])
            ->recordActions([
                // avançar status é ação, nunca um select dentro do formulário
                Action::make('avancar')
                    ->label(fn (Pedido $record) => match ($record->status) {
                        StatusPedido::Orcamento => 'Confirmar',
                        StatusPedido::Confirmado => 'Iniciar produção',
                        StatusPedido::EmProducao => 'Marcar como pronto',
                        StatusPedido::Pronto => 'Marcar como entregue',
                        default => 'Avançar',
                    })
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn (Pedido $record) => $record->status->proximo() !== null)
                    ->requiresConfirmation(fn (Pedido $record) => $record->status === StatusPedido::Orcamento)
                    ->modalHeading('Confirmar pedido')
                    ->modalDescription('Confirmar congela o custo dos itens e baixa o estoque de produto. É o momento em que a venda vira número no relatório.')
                    ->modalSubmitActionLabel('Confirmar')
                    ->action(function (Pedido $record, FinalizarPedido $servico) {
                        $proximo = $record->status->proximo();

                        if (! $proximo) {
                            return;
                        }

                        $pedido = $servico->mudarStatus($record, $proximo);

                        Notification::make()
                            ->title($pedido->identificacao.' — '.$proximo->getLabel())
                            ->body($pedido->jaBaixouEstoque()
                                ? 'Custo congelado em '.Decimal::paraReal($pedido->cmv()).'.'
                                : null)
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    EditAction::make(),

                    Action::make('voltar')
                        ->label('Voltar para orçamento')
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->color('gray')
                        ->visible(fn (Pedido $record) => ! $record->status->finalizado()
                            && $record->status !== StatusPedido::Orcamento)
                        ->requiresConfirmation()
                        ->modalDescription('O produto volta para o estoque e o pedido sai do faturamento.')
                        ->action(function (Pedido $record, FinalizarPedido $servico) {
                            $servico->mudarStatus($record, StatusPedido::Orcamento);

                            Notification::make()
                                ->title('Pedido voltou para orçamento')
                                ->body('Produto devolvido ao estoque.')
                                ->warning()
                                ->send();
                        }),

                    Action::make('cancelar')
                        ->label('Cancelar pedido')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->visible(fn (Pedido $record) => ! $record->status->finalizado())
                        ->requiresConfirmation()
                        ->modalHeading('Cancelar pedido')
                        ->modalDescription('O produto volta para o estoque e o pedido sai de todos os relatórios.')
                        ->modalSubmitActionLabel('Cancelar pedido')
                        ->action(function (Pedido $record, FinalizarPedido $servico) {
                            $servico->mudarStatus($record, StatusPedido::Cancelado);

                            Notification::make()
                                ->title('Pedido cancelado')
                                ->body('Produto devolvido ao estoque.')
                                ->danger()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn ($records) => $records->each(
                            fn (Pedido $pedido) => app(FinalizarPedido::class)->estornar($pedido)
                        )),
                ]),
            ])
            ->emptyStateHeading('Nenhum pedido')
            ->emptyStateDescription('Encomenda tem cliente e data de entrega. Venda avulsa é balcão e já sai finalizada.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
