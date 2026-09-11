<?php

namespace App\Filament\Resources\Movimentacoes\Tables;

use App\Enums\TipoMovimentacao;
use App\Models\Movimentacao;
use App\Support\Decimal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovimentacoesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('item')
                    ->label('Item')
                    ->state(fn (Movimentacao $record) => $record->item_nome)
                    ->description(fn (Movimentacao $record) => $record->insumo_id ? 'insumo' : 'produto')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('insumo', fn ($q) => $q->where('nome', 'like', "%{$search}%"))
                        ->orWhereHas('produto', fn ($q) => $q->where('nome', 'like', "%{$search}%"))),

                TextColumn::make('quantidade')
                    ->label('Quantidade')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, Movimentacao $record) => Decimal::paraBrEnxuto($state)
                        .' '.($record->insumo?->unidade_base->sufixo() ?? 'un'))
                    ->color(fn (Movimentacao $record) => match ($record->tipo) {
                        TipoMovimentacao::Entrada => 'success',
                        TipoMovimentacao::Perda => 'danger',
                        default => null,
                    }),

                TextColumn::make('custo_unitario')
                    ->label('Custo unit.')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => 'R$ '.Decimal::paraBr($state, 4))
                    ->toggleable(),

                TextColumn::make('valor')
                    ->label('Valor')
                    ->alignEnd()
                    ->state(fn (Movimentacao $record) => $record->valor())
                    ->money('BRL', locale: 'pt_BR'),

                TextColumn::make('observacao')
                    ->label('Observação')
                    ->wrap()
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoMovimentacao::class)
                    ->multiple(),

                Filter::make('so_perdas')
                    ->label('Só perdas')
                    ->query(fn (Builder $query) => $query->perdas())
                    ->toggle(),

                Filter::make('so_insumos')
                    ->label('Só insumos')
                    ->query(fn (Builder $query) => $query->deInsumos())
                    ->toggle(),

                Filter::make('so_produtos')
                    ->label('Só produtos')
                    ->query(fn (Builder $query) => $query->deProdutos())
                    ->toggle(),

                QueryBuilder::make()
                    ->constraints([
                        DateConstraint::make('created_at')->label('Data'),
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma movimentação ainda')
            ->emptyStateDescription('Cada compra, produção, venda, perda e ajuste deixa um registro aqui.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }
}
