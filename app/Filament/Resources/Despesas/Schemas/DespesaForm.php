<?php

namespace App\Filament\Resources\Despesas\Schemas;

use App\Enums\CategoriaDespesa;
use App\Filament\Forms\Components\CampoDinheiro;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class DespesaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // seção única: sem isso ela fica em meia largura, com a outra metade vazia
            ->columns(1)
            ->components([
                Section::make('Despesa')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('categoria')
                            ->label('Categoria')
                            ->options(CategoriaDespesa::class)
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->default(CategoriaDespesa::Outros)
                            ->validationMessages(['required' => 'Escolha a categoria da despesa.']),

                        CampoDinheiro::make('valor')
                            ->label('Valor')
                            ->maiorQueZero()
                            ->required()
                            ->validationMessages(['required' => 'Informe o valor da despesa.']),

                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->placeholder('Caixas de papelão para encomenda')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->validationMessages(['required' => 'Descreva a despesa.']),

                        DatePicker::make('data')
                            ->label('Data')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->validationMessages(['required' => 'Informe a data.']),

                        Toggle::make('recorrente')
                            ->label('Despesa recorrente')
                            ->helperText('Marque para aluguel, pró-labore, internet — o que repete todo mês.'),

                        Select::make('pedido_id')
                            ->label('Ligada a um pedido')
                            ->relationship('pedido', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->identificacao.' — '.($record->cliente?->nome ?? 'avulsa'))
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Opcional. Use para frete de uma entrega específica.'),
                    ])
                    ->footer([
                        Text::make('O pró-labore dos dois donos entra aqui como despesa recorrente. Sem isso o "lucro" é só o pagamento do trabalho de vocês disfarçado.')
                            ->color('gray'),
                    ]),
            ]);
    }
}
