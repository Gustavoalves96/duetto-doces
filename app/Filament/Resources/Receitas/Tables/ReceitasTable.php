<?php

namespace App\Filament\Resources\Receitas\Tables;

use App\Filament\Forms\Components\CampoQuantidade;
use App\Models\Receita;
use App\Services\ExecutarProducao;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ReceitasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('produto.nome')
                    ->label('Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Receita $record) => $record->nome),

                TextColumn::make('rendimento')
                    ->label('Rende')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => Decimal::paraBr($state, 3).' un'),

                TextColumn::make('itens_count')
                    ->label('Ingredientes')
                    ->counts('itens')
                    ->alignCenter(),

                TextColumn::make('custo_fornada')
                    ->label('Custo da fornada')
                    ->alignEnd()
                    ->state(fn (Receita $record) => $record->custoEstimadoFornada())
                    ->money('BRL', locale: 'pt_BR')
                    ->description(fn (Receita $record) => 'R$ '.Decimal::paraBr($record->custoEstimadoUnitario(), 4).' / un'),

                TextColumn::make('margem')
                    ->label('Margem estimada')
                    ->alignEnd()
                    ->badge()
                    ->state(function (Receita $record) {
                        $preco = $record->produto?->preco_venda;

                        if (! $preco || ! Decimal::positivo($preco)) {
                            return 'sem preço';
                        }

                        $margem = Decimal::sub($preco, $record->custoEstimadoUnitario());

                        return Decimal::paraBr(Decimal::mul(Decimal::div($margem, $preco), '100'), 1).'%';
                    })
                    ->color(function (Receita $record) {
                        $preco = $record->produto?->preco_venda;

                        if (! $preco || ! Decimal::positivo($preco)) {
                            return 'gray';
                        }

                        $margem = Decimal::mul(Decimal::div(
                            Decimal::sub($preco, $record->custoEstimadoUnitario()), $preco
                        ), '100');

                        return match (true) {
                            Decimal::menor($margem, '15') => 'danger',
                            Decimal::menor($margem, '30') => 'warning',
                            default => 'success',
                        };
                    }),
            ])
            ->filters([
                TernaryFilter::make('ativa')
                    ->label('Em uso')
                    ->default(true),
            ])
            ->recordActions([
                // Produção não é CRUD: é um evento disparado a partir da ficha.
                Action::make('produzir')
                    ->label('Produzir')
                    ->icon('heroicon-m-fire')
                    ->color('success')
                    ->button()
                    ->modalHeading(fn (Receita $record) => 'Produzir '.($record->produto?->nome ?? 'produto'))
                    ->modalDescription('Os insumos são baixados e o custo real do lote é calculado agora.')
                    ->modalSubmitActionLabel('Confirmar produção')
                    ->modalWidth('lg')
                    ->schema(fn (Receita $record) => [
                        CampoQuantidade::make('multiplicador')
                            ->label('Quantas fornadas')
                            ->maiorQueZero()
                            ->required()
                            ->default('1')
                            ->live(onBlur: true)
                            ->helperText('0,5 = meia fornada · 1 = uma fornada · 3 = três fornadas')
                            ->validationMessages(['required' => 'Informe o multiplicador da fornada.']),

                        Text::make(fn (Get $get) => new HtmlString(
                            self::previsao($record, (string) $get('multiplicador'))
                        )),

                        DatePicker::make('data')
                            ->label('Data da produção')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->required(),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->placeholder('Fornada da manhã')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (Receita $record, array $data, ExecutarProducao $servico) {
                        $multiplicador = (string) $data['multiplicador'];

                        // avisa, mas não bloqueia: estoque negativo é aceitável aqui
                        $faltando = $servico->insumosInsuficientes($record, $multiplicador);

                        $producao = $servico->executar(
                            $record,
                            $multiplicador,
                            $data['data'] ?? null,
                            $data['observacoes'] ?? null,
                        );

                        Notification::make()
                            ->title('Produção registrada')
                            ->body(
                                Decimal::paraBr($producao->quantidade_produzida, 3).' unidades a '
                                .Decimal::paraReal($producao->custoUnitarioLote()).' cada.'
                            )
                            ->success()
                            ->send();

                        if ($faltando !== []) {
                            $lista = collect($faltando)
                                ->map(fn ($i) => $i['nome'].' (faltou '.Decimal::paraBr($i['falta'], 3).' '.$i['unidade'].')')
                                ->implode(', ');

                            Notification::make()
                                ->title('Estoque ficou negativo')
                                ->body('Lance a compra destes insumos: '.$lista)
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhuma ficha técnica')
            ->emptyStateDescription('A ficha diz quais insumos entram e quantas unidades saem. Sem ela não existe custo de produto.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    /** Prévia do que a fornada vai consumir e custar, com os preços de hoje. */
    private static function previsao(Receita $receita, string $multiplicador): string
    {
        if (! Decimal::positivo(Decimal::de($multiplicador))) {
            return '<span class="text-sm text-gray-500">Informe o multiplicador para ver a prévia.</span>';
        }

        $multiplicador = Decimal::de($multiplicador);
        $linhas = [];
        $custo = '0';

        foreach ($receita->itens as $item) {
            $necessario = Decimal::mul($item->quantidade, $multiplicador);
            $sobra = Decimal::sub($item->insumo->estoque_atual, $necessario);
            $custo = Decimal::soma($custo, Decimal::mul($necessario, $item->insumo->custo_medio));

            $cor = Decimal::negativo($sobra) ? 'color:#dc2626;font-weight:600' : 'color:#6b7280';

            $linhas[] = '<li style="'.$cor.'">'
                .e($item->insumo->nome).': '
                .Decimal::paraBr($necessario, 3).' '.$item->insumo->unidade_base->sufixo()
                .' (sobra '.Decimal::paraBr($sobra, 3).')</li>';
        }

        $unidades = Decimal::mul($receita->rendimento, $multiplicador);
        $custoUnitario = Decimal::positivo($unidades) ? Decimal::div($custo, $unidades) : '0';

        return '<div class="text-sm">'
            .'<p style="font-weight:600;margin-bottom:.25rem">Vai consumir</p>'
            .'<ul style="margin:0 0 .5rem 1rem;list-style:disc">'.implode('', $linhas).'</ul>'
            .'<p>Rende <strong>'.Decimal::paraBr($unidades, 3).' un</strong> a '
            .'<strong>'.Decimal::paraReal($custoUnitario).'</strong> cada '
            .'(total '.Decimal::paraReal($custo).')</p>'
            .'</div>';
    }
}
