<?php

namespace App\Filament\Resources\Pedidos\Tables;

use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Filament\Resources\Pedidos\Actions\AcoesDeEncomenda;
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
            // no celular cada pedido vira um cartão; do tablet pra cima é tabela
            ->stackedOnMobile()
            ->defaultSort('id', 'desc')
            ->columns([
                // a data do pedido mora embaixo do número: uma coluna a menos
                // é o que faz a tabela caber no notebook e no tablet
                TextColumn::make('id')
                    ->label('Pedido')
                    ->formatStateUsing(fn (Pedido $record) => $record->identificacao)
                    ->description(fn (Pedido $record) => $record->data->format('d/m/y'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente.nome')
                    ->label('Cliente')
                    ->searchable()
                    ->wrap()
                    ->default('Balcão')
                    ->description(fn (Pedido $record) => $record->tipo->getLabel()),

                TextColumn::make('data_entrega')
                    ->label('Entrega')
                    ->date('d/m/y')
                    ->placeholder('—')
                    ->sortable()
                    // encomenda atrasada e ainda aberta precisa saltar aos olhos
                    ->color(fn (Pedido $record) => $record->data_entrega
                        && $record->data_entrega->isPast()
                        && ! $record->status->finalizado()
                            ? 'danger'
                            : null),

                self::colunaStatus(),

                // o total some só entre 1024 e 1279 px, onde o menu lateral
                // aberto rouba o espaço; o Filament não tem "só nesta faixa",
                // então são duas cópias da coluna, uma de cada lado da faixa
                self::colunaTotal('total')->hiddenFrom('lg'),
                self::colunaTotal('total_amplo')->visibleFrom('xl'),

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
                    ->visibleFrom('xl')
                    ->toggleable(),

                TextColumn::make('saldo')
                    ->label('A receber')
                    ->alignEnd()
                    ->state(fn (Pedido $record) => Decimal::paraReal($record->saldoAReceber()))
                    ->color(fn (Pedido $record) => Decimal::positivo($record->saldoAReceber()) ? 'warning' : 'success')
                    ->visibleFrom('xl')
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

                Filter::make('nao_pagas')
                    ->label('Encomendas não pagas')
                    ->query(fn (Builder $query) => $query
                        ->where('tipo', TipoPedido::Encomenda->value)
                        ->where('pago', false)
                        ->where('status', '!=', StatusPedido::Cancelado->value))
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
                // mudar status é ação, nunca um select dentro do formulário
                // no celular e no tablet vira só o ícone do caminhão
                AcoesDeEncomenda::entregar()->labeledFrom('xl'),

                ActionGroup::make([
                    EditAction::make(),

                    AcoesDeEncomenda::alternarPagamento(),

                    AcoesDeEncomenda::alterarEntrega(),

                    AcoesDeEncomenda::desfazerEntrega(),

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
            ->emptyStateDescription('Encomenda tem cliente e data de entrega; depois é só marcar como entregue. Venda avulsa é balcão e já sai finalizada.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    private static function colunaTotal(string $nome): TextColumn
    {
        return TextColumn::make($nome)
            ->label('Total')
            ->alignEnd()
            ->state(fn (Pedido $record) => $record->total())
            ->money('BRL', locale: 'pt_BR')
            ->description(fn (Pedido $record) => Decimal::positivo($record->taxa_operadora)
                ? 'líquido '.Decimal::paraReal($record->faturamentoLiquido())
                : null);
    }

    /**
     * Status do pedido e, se for encomenda, o pagamento embaixo, na mesma
     * célula. Numa coluna própria a tabela passava da tela do notebook.
     * Venda avulsa é paga no balcão e fica só com o status.
     */
    public static function colunaStatus(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->listWithLineBreaks()
            ->state(fn (Pedido $record) => array_values(array_filter([
                $record->status,
                $record->tipo === TipoPedido::Encomenda ? ($record->pago ? 'Pago' : 'Não pago') : null,
            ])))
            ->formatStateUsing(fn ($state) => $state instanceof StatusPedido ? $state->getLabel() : $state)
            ->color(fn ($state) => match (true) {
                $state instanceof StatusPedido => $state->getColor(),
                $state === 'Pago' => 'success',
                default => 'warning',
            })
            ->icon(fn ($state) => match (true) {
                $state instanceof StatusPedido => $state->getIcon(),
                $state === 'Pago' => 'heroicon-m-banknotes',
                default => 'heroicon-m-exclamation-circle',
            })
            ->sortable();
    }
}
