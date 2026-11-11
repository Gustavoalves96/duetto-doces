<?php

namespace App\Filament\Resources\Receitas\Tables;

use App\Filament\Forms\Components\CampoQuantidade;
use App\Models\Receita;
use App\Services\ExecutarProducao;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class ReceitasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // no celular cada linha vira um cartão; do tablet pra cima é tabela
            ->stackedOnMobile()
            ->defaultSort('id')
            ->columns([
                TextColumn::make('produto.nome')
                    ->label('Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Receita $record) => $record->nome),

                TextColumn::make('rendimento')
                    // só a partir do notebook; no tablet a linha não cabe
                    ->visibleFrom('xl')
                    ->label('Rende')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => Decimal::paraBrEnxuto($state).' un'),

                TextColumn::make('itens_count')
                    ->visibleFrom('xl')
                    ->label('Ingredientes')
                    ->counts('itens')
                    ->alignCenter(),

                TextColumn::make('custo_fornada')
                    ->label('Custo da fornada')
                    ->alignEnd()
                    ->state(fn (Receita $record) => $record->custoEstimadoFornada())
                    ->money('BRL', locale: 'pt_BR')
                    ->description(fn (Receita $record) => Decimal::paraRealLegivel($record->custoEstimadoUnitario()).' / un'),

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
                    // botão e não link: só botão sabe esconder o texto abaixo de 1536px,
                    // e sem isso a linha não cabe no tablet nem no notebook
                    ->button()
                    ->labeledFrom('2xl')
                    ->tooltip('Produzir')
                    ->label('Produzir')
                    ->icon('heroicon-m-fire')
                    ->color('success')
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

                        // um campo por recheio da ficha; o que não for recheado sai no produto da ficha
                        ...$record->recheios->map(fn ($recheio) => CampoQuantidade::make('recheios.'.$recheio->id)
                            ->label('Quantos com '.$recheio->insumo->nome.' ('.$recheio->produto->nome.')')
                            ->inteiro()
                            ->naoNegativo()
                            ->default('0')
                            ->suffix('un')
                            ->live(onBlur: true)
                        )->all(),

                        Text::make(fn (Get $get) => new HtmlString(
                            self::previsao($record, (string) $get('multiplicador'), (array) ($get('recheios') ?? []))
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
                        $recheios = (array) ($data['recheios'] ?? []);

                        // avisa, mas não bloqueia: estoque negativo é aceitável aqui
                        $faltando = $servico->insumosInsuficientes($record, $multiplicador, $recheios);

                        try {
                            $producao = $servico->executar(
                                $record,
                                $multiplicador,
                                $data['data'] ?? null,
                                $data['observacoes'] ?? null,
                                $recheios,
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Produção não registrada')->body($e->getMessage())->danger()->send();

                            throw new Halt;
                        }

                        Notification::make()
                            ->title('Produção registrada')
                            ->body($producao->movimentacoes()->deProdutos()->with('produto')->get()
                                ->map(fn ($mov) => Decimal::paraBrEnxuto($mov->quantidade).' '.$mov->produto->nome
                                    .' a '.Decimal::paraReal($mov->custo_unitario).' cada')
                                ->implode(' · '))
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

                EditAction::make()
                    ->button()
                    ->labeledFrom('2xl')
                    ->tooltip('Editar'),
            ])
            // sem exclusão em massa: ficha que já produziu é histórico; use "Em uso"
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma ficha técnica')
            ->emptyStateDescription('A ficha diz quais insumos entram e quantas unidades saem. Sem ela não existe custo de produto.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    /** Prévia do que a fornada vai consumir e custar, com os preços de hoje. */
    private static function previsao(Receita $receita, string $multiplicador, array $recheios = []): string
    {
        // chega mascarado, vindo direto do campo
        $multiplicador = Decimal::deBr($multiplicador);

        if (! Decimal::positivo($multiplicador)) {
            return '<span class="text-sm text-gray-500">Informe o multiplicador para ver a prévia.</span>';
        }
        $linhas = [];
        $custo = '0';

        $linha = function ($insumo, string $necessario) use (&$linhas) {
            $sobra = Decimal::sub($insumo->estoque_atual, $necessario);
            $cor = Decimal::negativo($sobra) ? 'color:#dc2626;font-weight:600' : 'color:#6b7280';

            $linhas[] = '<li style="'.$cor.'">'
                .e($insumo->nome).': '
                .Decimal::paraBr($necessario, 3).' '.$insumo->unidade_base->sufixo()
                .' (sobra '.Decimal::paraBr($sobra, 3).')</li>';
        };

        foreach ($receita->itens as $item) {
            $necessario = Decimal::mul($item->quantidade, $multiplicador);
            $custo = Decimal::soma($custo, Decimal::mul($necessario, $item->insumo->custo_medio));
            $linha($item->insumo, $necessario);
        }

        $unidades = Decimal::mul($receita->rendimento, $multiplicador);
        $custoMassaUn = Decimal::positivo($unidades) ? Decimal::div($custo, $unidades) : '0';
        $sobraUnidades = $unidades;
        $saidas = [];

        foreach ($receita->recheios as $recheio) {
            $qtd = Decimal::deBr($recheios[$recheio->id] ?? '0');

            if (! Decimal::positivo($qtd)) {
                continue;
            }

            $necessario = Decimal::mul($recheio->quantidade, $qtd);
            $custoRecheioUn = Decimal::mul($recheio->quantidade, $recheio->insumo->custo_medio);
            $custo = Decimal::soma($custo, Decimal::mul($necessario, $recheio->insumo->custo_medio));
            $sobraUnidades = Decimal::sub($sobraUnidades, $qtd);
            $linha($recheio->insumo, $necessario);

            $saidas[] = [$qtd, $recheio->produto->nome, Decimal::soma($custoMassaUn, $custoRecheioUn)];
        }

        if (Decimal::negativo($sobraUnidades)) {
            return '<p class="text-sm" style="color:#dc2626;font-weight:600">A fornada rende '
                .Decimal::paraBrEnxuto($unidades).' unidades; os recheios passam disso.</p>';
        }

        if (Decimal::positivo($sobraUnidades)) {
            array_unshift($saidas, [$sobraUnidades, $receita->produto?->nome ?? 'produto', $custoMassaUn]);
        }

        $rende = collect($saidas)
            ->map(fn ($s) => '<li><strong>'.Decimal::paraBrEnxuto($s[0]).' '.e($s[1]).'</strong> a '
                .'<strong>'.Decimal::paraReal($s[2]).'</strong> cada</li>')
            ->implode('');

        return '<div class="text-sm">'
            .'<p style="font-weight:600;margin-bottom:.25rem">Vai consumir</p>'
            .'<ul style="margin:0 0 .5rem 1rem;list-style:disc">'.implode('', $linhas).'</ul>'
            .'<p style="font-weight:600;margin-bottom:.25rem">Rende (total '.Decimal::paraReal($custo).')</p>'
            .'<ul style="margin:0 0 0 1rem;list-style:disc">'.$rende.'</ul>'
            .'</div>';
    }
}
