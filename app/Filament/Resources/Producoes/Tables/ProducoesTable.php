<?php

namespace App\Filament\Resources\Producoes\Tables;

use App\Models\Producao;
use App\Services\ExecutarProducao;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProducoesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // no celular cada linha vira um cartão; do tablet pra cima é tabela
            ->stackedOnMobile()
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('receita.produto'))
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('receita.produto.nome')
                    ->label('Produto')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Producao $record) => $record->receita?->nome),

                TextColumn::make('multiplicador')
                    // só a partir do notebook; no tablet a linha não cabe
                    ->visibleFrom('xl')
                    ->label('Fornadas')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => Decimal::paraBrEnxuto($state).'x'),

                TextColumn::make('quantidade_produzida')
                    ->label('Rendeu')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => Decimal::paraBrEnxuto($state).' un'),

                TextColumn::make('custo_total')
                    ->label('Custo')
                    ->money('BRL', locale: 'pt_BR')
                    ->alignEnd(),

                TextColumn::make('estornada_em')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (Producao $record) => $record->foiEstornada() ? 'Desfeita' : 'No estoque')
                    ->color(fn (Producao $record) => $record->foiEstornada() ? 'gray' : 'success'),
            ])
            ->recordActions([
                Action::make('desfazer')
                    // botão e não link: só botão sabe esconder o texto abaixo de 1536px
                    ->button()
                    ->labeledFrom('2xl')
                    ->tooltip('Desfazer')
                    ->label('Desfazer')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Producao $record) => ! $record->foiEstornada())
                    ->requiresConfirmation()
                    ->modalHeading('Desfazer produção')
                    ->modalDescription('Os insumos voltam para o estoque e as unidades produzidas saem. Use quando lançou a fornada errada. Fornada queimada é perda de produto, não estorno.')
                    ->modalSubmitActionLabel('Desfazer produção')
                    ->action(function (Producao $record, ExecutarProducao $servico) {
                        $servico->estornar($record);

                        Notification::make()
                            ->title('Produção desfeita')
                            ->body('Insumos devolvidos ao estoque e produto retirado.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma produção ainda')
            ->emptyStateDescription('As fornadas aparecem aqui depois de usar "Produzir" na ficha técnica.')
            ->emptyStateIcon('heroicon-o-fire');
    }
}
