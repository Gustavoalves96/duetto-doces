<?php

namespace App\Filament\Resources\Insumos\Tables;

use App\Filament\Forms\Components\CampoQuantidade;
use App\Models\Insumo;
use App\Services\MovimentarEstoque;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InsumosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')
                    ->label('Insumo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('estoque_atual')
                    ->label('Estoque')
                    ->sortable()
                    ->badge()
                    // vermelho no negativo, laranja no limite: é o alerta de compra
                    ->color(fn (Insumo $record) => match (true) {
                        $record->estaNegativo() => 'danger',
                        $record->estaAbaixoDoMinimo() => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn ($state, Insumo $record) => Decimal::paraBr($state, 3).' '.$record->unidade_base->sufixo()),

                TextColumn::make('estoque_minimo')
                    ->label('Mínimo')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state, Insumo $record) => Decimal::paraBr($state, 3).' '.$record->unidade_base->sufixo()),

                TextColumn::make('custo_medio')
                    ->label('Custo médio')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, Insumo $record) => 'R$ '.Decimal::paraBr($state, 4).' / '.$record->unidade_base->sufixo())
                    ->description(fn (Insumo $record) => 'R$ '.Decimal::paraBr(
                        Decimal::mul($record->custo_medio, (string) $record->unidade_base->fatorCompra()), 2
                    ).' / '.$record->unidade_base->unidadeCompra()),

                TextColumn::make('valor_em_estoque')
                    ->label('Valor parado')
                    ->alignEnd()
                    ->state(fn (Insumo $record) => $record->valorEmEstoque())
                    ->money('BRL', locale: 'pt_BR')
                    ->toggleable(),

                IconColumn::make('ativo')
                    ->label('Em uso')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('abaixo_do_minimo')
                    ->label('Precisa comprar')
                    ->query(fn (Builder $query) => $query->abaixoDoMinimo())
                    ->toggle(),

                Filter::make('negativos')
                    ->label('Estoque negativo')
                    ->query(fn (Builder $query) => $query->negativos())
                    ->toggle(),

                TernaryFilter::make('ativo')
                    ->label('Em uso')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('registrar_perda')
                    ->label('Perda')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->modalHeading(fn (Insumo $record) => 'Registrar perda de '.$record->nome)
                    ->modalDescription('Insumo vencido, embalagem rasgada, sobra que estragou.')
                    ->modalSubmitActionLabel('Registrar perda')
                    ->schema(fn (Insumo $record) => [
                        Text::make('Estoque atual: '.Decimal::paraBr($record->estoque_atual, 3).' '.$record->unidade_base->sufixo()),

                        CampoQuantidade::make('quantidade')
                            ->label('Quantidade perdida')
                            ->suffix($record->unidade_base->sufixo())
                            ->maiorQueZero()
                            ->required()
                            ->validationMessages(['required' => 'Informe quanto foi perdido.']),

                        Textarea::make('observacao')
                            ->label('O que aconteceu')
                            ->placeholder('Chocolate vencido no fundo do armário')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Insumo $record, array $data, MovimentarEstoque $servico) {
                        $movimentacao = $servico->perdaDeInsumo(
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
                    ->modalHeading(fn (Insumo $record) => 'Contagem de '.$record->nome)
                    ->modalDescription('Informe o que realmente tem na despensa. O sistema grava a diferença.')
                    ->modalSubmitActionLabel('Ajustar')
                    ->schema(fn (Insumo $record) => [
                        Text::make('Sistema diz: '.Decimal::paraBr($record->estoque_atual, 3).' '.$record->unidade_base->sufixo()),

                        CampoQuantidade::make('saldo_real')
                            ->label('Quantidade real contada')
                            ->suffix($record->unidade_base->sufixo())
                            ->required()
                            ->default(fn () => $record->estoque_atual)
                            ->validationMessages(['required' => 'Informe o saldo contado.']),

                        Textarea::make('observacao')
                            ->label('Motivo')
                            ->placeholder('Contagem do fim do mês')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Insumo $record, array $data, MovimentarEstoque $servico) {
                        $movimentacao = $servico->ajustarInsumo(
                            $record,
                            (string) $data['saldo_real'],
                            $data['observacao'] ?? null,
                        );

                        Notification::make()
                            ->title('Estoque ajustado')
                            ->body('Diferença de '.Decimal::paraBr($movimentacao->quantidade, 3).' '.$record->unidade_base->sufixo().'.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhum insumo cadastrado')
            ->emptyStateDescription('Comece pelos ingredientes que você compra: farinha, chocolate, manteiga, ovos.')
            ->emptyStateIcon('heroicon-o-cube');
    }
}
