<?php

namespace App\Filament\Resources\Receitas\Schemas;

use App\Filament\Forms\Components\CampoDinheiro;
use App\Filament\Forms\Components\CampoQuantidade;
use App\Models\Insumo;
use App\Models\Receita;
use App\Support\Decimal;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ReceitaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ficha técnica')
                    ->columns(3)
                    ->schema([
                        Select::make('produto_id')
                            ->label('Produto que sai desta receita')
                            ->relationship('produto', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(2)
                            ->validationMessages(['required' => 'Escolha o produto produzido.'])
                            ->createOptionForm([
                                TextInput::make('nome')->label('Nome')->required()->maxLength(255),
                                CampoDinheiro::make('preco_venda')
                                    ->label('Preço de venda')->maiorQueZero()->required(),
                            ]),

                        CampoQuantidade::make('rendimento')
                            ->label('Rende quantas unidades')
                            ->suffix('un')
                            ->maiorQueZero()
                            ->required()
                            ->default('1')
                            ->helperText('Por fornada inteira.')
                            ->validationMessages(['required' => 'Informe quantas unidades a fornada rende.']),

                        TextInput::make('nome')
                            ->label('Nome da ficha')
                            ->placeholder('Brownie tradicional — fornada da forma grande')
                            ->maxLength(255)
                            ->helperText('Opcional. Serve para diferenciar variações do mesmo produto.')
                            ->columnSpan(2),

                        Toggle::make('ativa')
                            ->label('Ficha em uso')
                            ->default(true),
                    ]),

                Section::make('Ingredientes')
                    ->description('Quantidades de UMA fornada, na unidade de estoque do insumo. Meia fornada ou três fornadas saem do multiplicador na hora de produzir.')
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
                            ->addActionLabel('Adicionar ingrediente')
                            ->itemLabel(fn (array $state) => Insumo::find($state['insumo_id'] ?? null)?->nome)
                            ->schema([
                                Select::make('insumo_id')
                                    ->label('Insumo')
                                    ->relationship('insumo', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->distinct()
                                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 6])
                                    ->validationMessages([
                                        'required' => 'Escolha o insumo.',
                                        'distinct' => 'Este insumo já está na ficha.',
                                    ]),

                                CampoQuantidade::make('quantidade')
                                    ->label('Quantidade')
                                    ->maiorQueZero()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->suffix(fn (Get $get) => Insumo::find($get('insumo_id'))?->unidade_base->sufixo())
                                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 3])
                                    ->validationMessages(['required' => 'Informe a quantidade.']),

                                Placeholder::make('custo_linha')
                                    ->label('Custo hoje')
                                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 3])
                                    ->content(function (Get $get) {
                                        $insumo = Insumo::find($get('insumo_id'));

                                        if (! $insumo) {
                                            return '—';
                                        }

                                        return 'R$ '.Decimal::paraBr(
                                            Decimal::mul(Decimal::de($get('quantidade')), $insumo->custo_medio)
                                        );
                                    }),
                            ]),
                    ]),

                Section::make('Custo estimado')
                    ->description('Projeção com os custos médios de hoje. O custo real é o que a produção gravar.')
                    ->columns(3)
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('custo_fornada')
                            ->label('Custo da fornada')
                            ->content(fn (?Receita $record) => Decimal::paraReal($record?->custoEstimadoFornada() ?? '0')),

                        Placeholder::make('custo_unidade')
                            ->label('Custo por unidade')
                            ->content(fn (?Receita $record) => 'R$ '.Decimal::paraBr($record?->custoEstimadoUnitario() ?? '0', 4)),

                        Placeholder::make('margem_estimada')
                            ->label('Margem no preço atual')
                            ->content(function (?Receita $record) {
                                $preco = $record?->produto?->preco_venda;

                                if (! $preco || ! Decimal::positivo($preco)) {
                                    return '—';
                                }

                                $margem = Decimal::sub($preco, $record->custoEstimadoUnitario());

                                return Decimal::paraBr(
                                    Decimal::mul(Decimal::div($margem, $preco), '100'), 1
                                ).'%';
                            }),
                    ])
                    ->footer([
                        Text::make('Para transformar isso em produto acabado, use a ação "Produzir" na listagem de fichas.')
                            ->color('gray'),
                    ]),

                Section::make('Modo de preparo / observações')
                    ->collapsed()
                    ->schema([
                        Textarea::make('observacoes')
                            ->hiddenLabel()
                            ->placeholder('Forno a 180 °C por 25 minutos. Não bater demais depois da farinha.')
                            ->rows(5)
                            ->maxLength(2000),
                    ]),
            ]);
    }
}
