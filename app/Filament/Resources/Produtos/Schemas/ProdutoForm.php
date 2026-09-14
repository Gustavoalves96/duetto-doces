<?php

namespace App\Filament\Resources\Produtos\Schemas;

use App\Filament\Forms\Components\CampoDinheiro;
use App\Models\Produto;
use App\Support\Decimal;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class ProdutoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produto')
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do produto')
                            ->placeholder('Brownie tradicional')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'O nome do produto é obrigatório.',
                                'unique' => 'Já existe um produto com esse nome.',
                            ])
                            ->columnSpanFull(),

                        CampoDinheiro::make('preco_venda')
                            ->label('Preço de venda')
                            ->maiorQueZero()
                            ->required()
                            ->helperText('Quanto o cliente paga por unidade.')
                            ->validationMessages(['required' => 'Informe o preço de venda.']),

                        Toggle::make('ativo')
                            ->label('Produto à venda')
                            ->default(true)
                            ->helperText('Desligue para tirar das listas sem apagar o histórico.'),
                    ]),

                Section::make('Custo e margem')
                    ->description('Calculado pela ficha técnica. Não dá para digitar — e é isso que faz os relatórios valerem alguma coisa.')
                    ->columns(['default' => 1, 'sm' => 3])
                    ->visibleOn('edit')
                    ->schema([
                        CampoDinheiro::make('custo_unitario')
                            ->label('Custo por unidade')
                            ->casas(4)
                            ->disabled()
                            ->dehydrated(false),

                        Placeholder::make('margem_reais')
                            ->label('Margem por unidade')
                            ->content(fn (?Produto $record) => Decimal::paraReal($record?->margemUnitaria() ?? '0')),

                        Placeholder::make('margem_percentual')
                            ->label('Margem')
                            ->content(fn (?Produto $record) => Decimal::paraBr($record?->margemPercentual() ?? '0', 1).'%'),

                        Placeholder::make('estoque_atual')
                            ->label('Em estoque')
                            ->content(fn (?Produto $record) => Decimal::paraBr($record?->estoque_atual ?? '0', 3).' un'),
                    ])
                    ->footer([
                        Text::make('O custo só aparece depois da primeira produção. Cadastre a ficha técnica em Receitas e use a ação "Produzir".')
                            ->color('gray'),
                    ]),
            ]);
    }
}
