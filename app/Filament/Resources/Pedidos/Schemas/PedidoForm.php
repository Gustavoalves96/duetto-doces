<?php

namespace App\Filament\Resources\Pedidos\Schemas;

use App\Enums\FormaPagamento;
use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Filament\Forms\Components\CampoDinheiro;
use App\Filament\Forms\Components\CampoQuantidade;
use App\Filament\Forms\Components\CampoTelefone;
use App\Models\Pedido;
use App\Models\Produto;
use App\Support\Decimal;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PedidoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pedido')
                    ->columns(3)
                    ->schema([
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoPedido::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->default(TipoPedido::Avulsa)
                            ->disabledOn('edit')
                            ->helperText('Encomenda tem data de entrega e passa pelo fluxo de status. Avulsa é venda de balcão, já finalizada.')
                            ->validationMessages(['required' => 'Escolha o tipo do pedido.'])
                            // o status inicial sai do tipo; ele não é editável no formulário
                            ->afterStateUpdated(fn (Set $set, $state) => $set(
                                'status',
                                TipoPedido::tryFrom((string) ($state instanceof TipoPedido ? $state->value : $state)) === TipoPedido::Encomenda
                                    ? StatusPedido::Orcamento->value
                                    : StatusPedido::Entregue->value
                            )),

                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'nome')
                            ->searchable()
                            ->preload()
                            ->columnSpan(2)
                            ->required(fn (Get $get) => self::ehEncomenda($get))
                            ->helperText('Venda de balcão pode ficar sem cliente.')
                            ->validationMessages(['required' => 'Encomenda precisa de cliente.'])
                            ->createOptionForm([
                                TextInput::make('nome')->label('Nome')->required()->maxLength(255),
                                CampoTelefone::make('telefone')->label('WhatsApp'),
                            ]),

                        DatePicker::make('data')
                            ->label('Data do pedido')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->validationMessages(['required' => 'Informe a data do pedido.']),

                        DatePicker::make('data_entrega')
                            ->label('Data de entrega')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->visible(fn (Get $get) => self::ehEncomenda($get))
                            ->required(fn (Get $get) => self::ehEncomenda($get))
                            ->minDate(fn (Get $get) => $get('data'))
                            ->validationMessages([
                                'required' => 'Encomenda precisa de data de entrega.',
                                'after_or_equal' => 'A entrega não pode ser antes do pedido.',
                            ]),

                        // status vive nas ações da listagem; aqui só o valor inicial
                        Hidden::make('status')
                            ->default(fn (Get $get) => self::ehEncomenda($get)
                                ? StatusPedido::Orcamento->value
                                : StatusPedido::Entregue->value),
                    ]),

                Section::make('Itens')
                    ->description('O custo de cada item é congelado quando o pedido sai de orçamento. Depois disso ele não muda mais, nem se o chocolate subir.')
                    ->schema([
                        Repeater::make('itens')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 12,
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Adicionar produto')
                            ->itemLabel(fn (array $state) => Produto::find($state['produto_id'] ?? null)?->nome)
                            ->schema([
                                Select::make('produto_id')
                                    ->label('Produto')
                                    ->relationship('produto', 'nome', fn ($query) => $query->where('ativo', true))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 5])
                                    ->validationMessages(['required' => 'Escolha o produto.'])
                                    // puxa o preço de tabela, mas deixa editar (combinado é combinado)
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if ($produto = Produto::find($state)) {
                                            $set('preco_unitario', Decimal::paraBr($produto->preco_venda));
                                        }
                                    }),

                                CampoQuantidade::make('quantidade')
                                    ->label('Qtd')
                                    ->suffix('un')
                                    ->maiorQueZero()
                                    ->required()
                                    ->default('1')
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 2])
                                    ->validationMessages(['required' => 'Informe a quantidade.']),

                                CampoDinheiro::make('preco_unitario')
                                    ->label('Preço un.')
                                    ->maiorQueZero()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 2])
                                    ->validationMessages(['required' => 'Informe o preço unitário.']),

                                Placeholder::make('total_linha')
                                    ->label('Total')
                                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 3])
                                    ->content(fn (Get $get) => Decimal::paraReal(
                                        Decimal::mul(Decimal::de($get('quantidade')), Decimal::de($get('preco_unitario')))
                                    )),
                            ]),
                    ]),

                Section::make('Pagamento')
                    ->columns(4)
                    ->schema([
                        Select::make('forma_pagamento')
                            ->label('Forma de pagamento')
                            ->options(FormaPagamento::class)
                            ->native(false)
                            ->live()
                            ->columnSpan(2)
                            // sugere a taxa da maquininha; o valor real fica editável
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $forma = $state instanceof FormaPagamento
                                    ? $state
                                    : FormaPagamento::tryFrom((string) $state);

                                if (! $forma) {
                                    return;
                                }

                                $bruto = Decimal::sub(self::subtotalDoForm($get), Decimal::de($get('desconto')));

                                $set('taxa_operadora', Decimal::paraBr(
                                    Decimal::percentual($bruto, $forma->taxaSugerida())
                                ));
                            }),

                        CampoDinheiro::make('taxa_operadora')
                            ->label('Taxa da operadora')
                            ->naoNegativo()
                            ->default('0')
                            ->live(onBlur: true)
                            ->columnSpan(2)
                            ->helperText('Maquininha, delivery. Sai do faturamento líquido.'),

                        CampoDinheiro::make('desconto')
                            ->label('Desconto')
                            ->naoNegativo()
                            ->default('0')
                            ->live(onBlur: true)
                            ->columnSpan(2),

                        CampoDinheiro::make('sinal_pago')
                            ->label('Sinal já pago')
                            ->naoNegativo()
                            ->default('0')
                            ->live(onBlur: true)
                            ->columnSpan(2)
                            ->visible(fn (Get $get) => self::ehEncomenda($get))
                            ->helperText('Entrada que o cliente deixou.'),
                    ]),

                Section::make('Conferência')
                    ->columns(4)
                    ->schema([
                        Placeholder::make('resumo_subtotal')
                            ->label('Subtotal')
                            ->content(fn (Get $get) => Decimal::paraReal(self::subtotalDoForm($get))),

                        Placeholder::make('resumo_total')
                            ->label('Total do cliente')
                            ->content(fn (Get $get) => Decimal::paraReal(
                                Decimal::sub(self::subtotalDoForm($get), Decimal::de($get('desconto')))
                            )),

                        Placeholder::make('resumo_liquido')
                            ->label('Entra no caixa')
                            ->content(fn (Get $get) => Decimal::paraReal(Decimal::sub(
                                Decimal::sub(self::subtotalDoForm($get), Decimal::de($get('desconto'))),
                                Decimal::de($get('taxa_operadora'))
                            ))),

                        Placeholder::make('resumo_saldo')
                            ->label('Falta receber')
                            ->visible(fn (Get $get) => self::ehEncomenda($get))
                            ->content(fn (Get $get) => Decimal::paraReal(Decimal::sub(
                                Decimal::sub(self::subtotalDoForm($get), Decimal::de($get('desconto'))),
                                Decimal::de($get('sinal_pago'))
                            ))),

                        Text::make(fn (?Pedido $record) => $record && $record->jaBaixouEstoque()
                            ? 'Custo congelado: '.Decimal::paraReal($record->cmv())
                                .' · margem de contribuição: '.Decimal::paraReal($record->margemContribuicao())
                            : 'O custo é congelado quando o pedido deixa de ser orçamento.')
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),

                Section::make('Observações')
                    ->collapsed()
                    ->schema([
                        Textarea::make('observacoes')
                            ->hiddenLabel()
                            ->placeholder('Sem nozes. Entregar na portaria.')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
            ]);
    }

    private static function ehEncomenda(Get $get): bool
    {
        $tipo = $get('tipo');

        return ($tipo instanceof TipoPedido ? $tipo : TipoPedido::tryFrom((string) $tipo)) === TipoPedido::Encomenda;
    }

    /** Soma os itens que estão no formulário agora, sem passar pelo banco. */
    private static function subtotalDoForm(Get $get): string
    {
        $total = '0';

        foreach ((array) $get('itens') as $item) {
            $total = Decimal::soma($total, Decimal::mul(
                Decimal::de($item['quantidade'] ?? 0),
                Decimal::de($item['preco_unitario'] ?? 0),
            ));
        }

        return Decimal::dinheiro($total);
    }
}
