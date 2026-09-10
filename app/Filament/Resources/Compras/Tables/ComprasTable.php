<?php

namespace App\Filament\Resources\Compras\Tables;

use App\Models\Compra;
use App\Services\RegistrarCompra;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComprasTable
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

                TextColumn::make('fornecedor')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('itens_count')
                    ->label('Itens')
                    ->counts('itens')
                    ->alignCenter(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL', locale: 'pt_BR')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('aplicada_em')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (Compra $record) => $record->jaAplicada() ? 'No estoque' : 'Estornada')
                    ->color(fn (Compra $record) => $record->jaAplicada() ? 'success' : 'gray'),
            ])
            ->filters([
                Filter::make('estornadas')
                    ->label('Só estornadas')
                    ->query(fn (Builder $query) => $query->whereNull('aplicada_em'))
                    ->toggle(),

                QueryBuilder::make()
                    ->constraints([
                        DateConstraint::make('data')->label('Data'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('estornar')
                    ->label('Estornar')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Compra $record) => $record->jaAplicada())
                    ->requiresConfirmation()
                    ->modalHeading('Estornar compra')
                    ->modalDescription('A quantidade sai do estoque e o custo médio é refeito sem esta nota. Use quando lançou algo errado.')
                    ->modalSubmitActionLabel('Estornar')
                    ->action(function (Compra $record, RegistrarCompra $servico) {
                        $servico->estornar($record);

                        Notification::make()
                            ->title('Compra estornada')
                            ->body('Agora dá para corrigir os itens e salvar de novo.')
                            ->warning()
                            ->send();
                    }),

                Action::make('aplicar')
                    ->label('Lançar no estoque')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Compra $record) => ! $record->jaAplicada())
                    ->action(function (Compra $record, RegistrarCompra $servico) {
                        $compra = $servico->aplicar($record);

                        Notification::make()
                            ->title('Compra lançada')
                            ->body('Total de '.Decimal::paraReal($compra->total).'.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // nunca apagar sem devolver o efeito no estoque
                        ->before(fn ($records) => $records->each(
                            fn (Compra $compra) => app(RegistrarCompra::class)->estornar($compra)
                        )),
                ]),
            ])
            ->emptyStateHeading('Nenhuma compra registrada')
            ->emptyStateDescription('Lance a nota do mercado aqui. É o que alimenta o custo médio dos insumos.')
            ->emptyStateIcon('heroicon-o-shopping-cart');
    }
}
