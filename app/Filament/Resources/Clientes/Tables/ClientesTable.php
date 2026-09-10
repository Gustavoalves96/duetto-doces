<?php

namespace App\Filament\Resources\Clientes\Tables;

use App\Models\Cliente;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->formatStateUsing(fn (Cliente $record) => $record->telefoneFormatado() ?? '—')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Telefone copiado'),

                TextColumn::make('pedidos_count')
                    ->label('Pedidos')
                    ->counts('pedidos')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('observacoes')
                    ->label('Observações')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhum cliente cadastrado')
            ->emptyStateDescription('Cadastre quem faz encomenda. Venda avulsa no balcão não precisa de cliente.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
