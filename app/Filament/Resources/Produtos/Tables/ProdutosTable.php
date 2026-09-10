<?php

namespace App\Filament\Resources\Produtos\Tables;

use App\Filament\Forms\Components\CampoQuantidade;
use App\Models\Produto;
use App\Services\MovimentarEstoque;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProdutosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')
                    ->label('Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('preco_venda')
                    ->label('Preço')
                    ->sortable()
                    ->alignEnd()
                    ->money('BRL', locale: 'pt_BR'),

                TextColumn::make('custo_unitario')
                    ->label('Custo')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => 'R$ '.Decimal::paraBr($state, 4))
                    ->description(fn (Produto $record) => Decimal::ehZero($record->custo_unitario)
                        ? 'sem produção ainda'
                        : null)
                    ->color(fn (Produto $record) => Decimal::ehZero($record->custo_unitario) ? 'gray' : null),

                TextColumn::make('margem')
                    ->label('Margem')
                    ->alignEnd()
                    ->badge()
                    ->state(fn (Produto $record) => Decimal::paraBr($record->margemPercentual(), 1).'%')
                    // abaixo de 30% o sabor está trabalhando de graça
                    ->color(fn (Produto $record) => match (true) {
                        Decimal::ehZero($record->custo_unitario) => 'gray',
                        Decimal::menor($record->margemPercentual(), '15') => 'danger',
                        Decimal::menor($record->margemPercentual(), '30') => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Produto $record) => 'R$ '.Decimal::paraBr($record->margemUnitaria()).' / un'),

                TextColumn::make('estoque_atual')
                    ->label('Estoque')
                    ->sortable()
                    ->badge()
                    ->color(fn (Produto $record) => Decimal::negativo($record->estoque_atual) ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => Decimal::paraBr($state, 3).' un'),
            ])
            ->filters([
                TernaryFilter::make('ativo')
                    ->label('À venda')
                    ->default(true),

                Filter::make('sem_custo')
                    ->label('Sem custo calculado')
                    ->query(fn (Builder $query) => $query->where('custo_unitario', '<=', 0))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('registrar_perda')
                    ->label('Perda')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->modalHeading(fn (Produto $record) => 'Registrar perda de '.$record->nome)
                    ->modalDescription('Fornada queimada, produto que passou do ponto, caiu no chão.')
                    ->modalSubmitActionLabel('Registrar perda')
                    ->schema(fn (Produto $record) => [
                        Text::make('Estoque atual: '.Decimal::paraBr($record->estoque_atual, 3).' un'),

                        CampoQuantidade::make('quantidade')
                            ->label('Quantidade perdida')
                            ->suffix('un')
                            ->maiorQueZero()
                            ->required()
                            ->validationMessages(['required' => 'Informe quanto foi perdido.']),

                        Textarea::make('observacao')
                            ->label('O que aconteceu')
                            ->placeholder('Fornada queimou')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Produto $record, array $data, MovimentarEstoque $servico) {
                        $movimentacao = $servico->perdaDeProduto(
                            $record,
                            (string) $data['quantidade'],
                            $data['observacao'] ?? null,
                        );

                        Notification::make()
                            ->title('Perda registrada')
                            ->body('Prejuízo de '.Decimal::paraReal($movimentacao->valor()).'.')
                            ->warning()
                            ->send();
                    }),

                Action::make('ajustar_estoque')
                    ->label('Ajustar')
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->color('warning')
                    ->modalHeading(fn (Produto $record) => 'Contagem de '.$record->nome)
                    ->modalDescription('Informe quantas unidades realmente existem.')
                    ->modalSubmitActionLabel('Ajustar')
                    ->schema(fn (Produto $record) => [
                        Text::make('Sistema diz: '.Decimal::paraBr($record->estoque_atual, 3).' un'),

                        CampoQuantidade::make('saldo_real')
                            ->label('Quantidade real contada')
                            ->suffix('un')
                            ->required()
                            ->default(fn () => $record->estoque_atual)
                            ->validationMessages(['required' => 'Informe o saldo contado.']),

                        Textarea::make('observacao')
                            ->label('Motivo')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Produto $record, array $data, MovimentarEstoque $servico) {
                        $movimentacao = $servico->ajustarProduto(
                            $record,
                            (string) $data['saldo_real'],
                            $data['observacao'] ?? null,
                        );

                        Notification::make()
                            ->title('Estoque ajustado')
                            ->body('Diferença de '.Decimal::paraBr($movimentacao->quantidade, 3).' un.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhum produto cadastrado')
            ->emptyStateDescription('Cadastre o que você vende: brownie tradicional, cookie de chocolate, etc.')
            ->emptyStateIcon('heroicon-o-cake');
    }
}
