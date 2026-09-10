<?php

namespace App\Filament\Resources\Despesas\Tables;

use App\Enums\CategoriaDespesa;
use App\Models\Despesa;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DespesasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data', 'desc')
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->sortable(),

                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL', locale: 'pt_BR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('BRL', locale: 'pt_BR')),

                IconColumn::make('recorrente')
                    ->label('Recorrente')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('pedido.id')
                    ->label('Pedido')
                    ->formatStateUsing(fn (Despesa $record) => $record->pedido?->identificacao ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options(CategoriaDespesa::class)
                    ->multiple(),

                TernaryFilter::make('recorrente')
                    ->label('Recorrente'),

                QueryBuilder::make()
                    ->constraints([
                        DateConstraint::make('data')->label('Data'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),

                // aluguel e pró-labore repetem todo mês: duplicar poupa digitação
                ReplicateAction::make()
                    ->label('Repetir')
                    ->icon('heroicon-m-document-duplicate')
                    ->visible(fn (Despesa $record) => $record->recorrente)
                    ->beforeReplicaSaved(function (Despesa $replica, Despesa $record) {
                        $replica->data = $record->data->copy()->addMonth();
                    })
                    ->successNotificationTitle('Despesa repetida para o mês seguinte'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhuma despesa lançada')
            ->emptyStateDescription('Aluguel, energia, embalagem, pró-labore. Sem elas o lucro do relatório é fantasia.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
