<?php

namespace App\Filament\Resources\Insumos\Schemas;

use App\Enums\UnidadeBase;
use App\Filament\Forms\Components\CampoDinheiro;
use App\Filament\Forms\Components\CampoQuantidade;
use App\Models\Insumo;
use App\Support\Decimal;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class InsumoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do insumo')
                            ->placeholder('Chocolate meio amargo 50%')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'O nome do insumo é obrigatório.',
                                'unique' => 'Já existe um insumo com esse nome.',
                            ])
                            ->columnSpanFull(),

                        Select::make('unidade_base')
                            ->label('Unidade de estoque')
                            ->options(UnidadeBase::class)
                            ->required()
                            ->native(false)
                            ->default(UnidadeBase::Grama)
                            // trocar a unidade depois invalidaria todo o estoque e as fichas
                            ->disabledOn('edit')
                            ->helperText('Sempre a menor unidade. Compra em kg? Cadastre em gramas.')
                            ->validationMessages(['required' => 'Escolha a unidade de estoque.']),

                        CampoQuantidade::make('estoque_minimo')
                            ->label('Estoque mínimo')
                            ->naoNegativo()
                            ->required()
                            ->default('0')
                            ->suffix(fn ($get) => UnidadeBase::resolver($get('unidade_base'))?->sufixo())
                            ->live()
                            ->helperText('Abaixo disso o insumo aparece em vermelho no painel.')
                            ->validationMessages(['required' => 'Informe o estoque mínimo, nem que seja 0.']),

                        Toggle::make('ativo')
                            ->label('Insumo em uso')
                            ->default(true)
                            ->helperText('Desligue para sumir das listas sem apagar o histórico.'),
                    ]),

                Section::make('Saldo inicial')
                    ->description('Só na criação. Depois disso o saldo muda por compra, produção, perda ou ajuste.')
                    ->columns(2)
                    ->visibleOn('create')
                    ->schema([
                        CampoQuantidade::make('estoque_atual')
                            ->label('Quantidade já em casa')
                            ->default('0')
                            ->suffix(fn ($get) => UnidadeBase::resolver($get('unidade_base'))?->sufixo())
                            ->helperText('O que estiver na despensa hoje. Pode deixar 0.'),

                        CampoDinheiro::make('custo_medio')
                            ->label('Custo por unidade')
                            ->casas(4)
                            ->naoNegativo()
                            ->default('0')
                            ->helperText('Quanto custa 1 unidade da medida acima. Ex.: R$ 0,0348 por grama.'),
                    ]),

                Section::make('Situação atual')
                    ->columns(3)
                    ->visibleOn('edit')
                    ->schema([
                        CampoQuantidade::make('estoque_atual')
                            ->label('Estoque atual')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix(fn ($record) => $record?->unidade_base?->sufixo()),

                        CampoDinheiro::make('custo_medio')
                            ->label('Custo médio')
                            ->casas(4)
                            ->disabled()
                            ->dehydrated(false),

                        Placeholder::make('valor_parado')
                            ->label('Valor em estoque')
                            ->content(fn (?Insumo $record) => Decimal::paraReal($record?->valorEmEstoque() ?? '0')),
                    ])
                    ->footer([
                        Text::make(
                            'Estes números são calculados. Para corrigir, use "Ajustar estoque" ou "Registrar perda" na listagem.'
                        )->color('gray'),
                    ]),
            ]);
    }
}
