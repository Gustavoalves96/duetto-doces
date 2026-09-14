<?php

namespace App\Filament\Resources\Compras\Schemas;

use App\Enums\UnidadeBase;
use App\Filament\Forms\Components\CampoDinheiro;
use App\Filament\Forms\Components\CampoQuantidade;
use App\Models\Compra;
use App\Models\Insumo;
use App\Support\Decimal;
use Filament\Forms\Components\DatePicker;
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

class CompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // coluna única: o padrão de 2 colunas põe cada seção em meia largura,
            // e o repeater de itens não cabe nisso num notebook
            ->columns(1)
            ->components([
                Section::make('Nota')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('fornecedor')
                            ->label('Fornecedor')
                            ->placeholder('Atacadão')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages(['required' => 'Informe o fornecedor.']),

                        DatePicker::make('data')
                            ->label('Data da compra')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->maxDate(now()->addDay())
                            ->validationMessages(['required' => 'Informe a data da compra.']),

                        Placeholder::make('total')
                            // soma dos itens; o serviço regrava no banco ao aplicar
                            ->label('Total da nota')
                            ->content(fn (?Compra $record) => Decimal::paraReal($record?->total ?? '0')),
                    ]),

                Section::make('Itens comprados')
                    ->description('Informe a quantidade na unidade da embalagem. A conversão para grama ou mililitro é automática.')
                    ->schema([
                        Repeater::make('itens')
                            ->relationship()
                            ->hiddenLabel()
                            // degraus por largura de TELA (o Tailwind não conhece a do
                            // container): 12 colunas só em xl; em lg, com a barra
                            // lateral aberta, o conteúdo tem ~700px e 6 colunas bastam
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'lg' => 6,
                                'xl' => 12,
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Adicionar item')
                            ->reorderable(false)
                            // compra já aplicada não pode ter item mexido: o custo médio
                            // é acumulativo e não dá para "reescrever" o passado.
                            // Para corrigir, use Estornar na listagem.
                            ->disabled(fn (?Compra $record) => $record?->jaAplicada() ?? false)
                            ->itemLabel(fn (array $state) => Insumo::find($state['insumo_id'] ?? null)?->nome)
                            ->schema([
                                Select::make('insumo_id')
                                    ->label('Insumo')
                                    ->relationship('insumo', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->columnSpan(['default' => 1, 'md' => 2, 'lg' => 2, 'xl' => 4])
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        $insumo = Insumo::find($state);
                                        // já entra na unidade da embalagem (kg, L, un)
                                        $set('unidade_entrada', $insumo
                                            ? array_key_first($insumo->unidade_base->opcoesEntrada())
                                            : null);
                                    })
                                    ->validationMessages(['required' => 'Escolha o insumo.'])
                                    ->createOptionForm([
                                        TextInput::make('nome')->label('Nome')->required()->maxLength(255),
                                        Select::make('unidade_base')
                                            ->label('Unidade de estoque')
                                            ->options(UnidadeBase::class)
                                            ->default(UnidadeBase::Grama)
                                            ->required()
                                            ->native(false),
                                    ]),

                                Select::make('unidade_entrada')
                                    ->label('Unidade')
                                    ->options(fn (Get $get) => self::insumoDe($get)?->unidade_base->opcoesEntrada() ?? [])
                                    ->default(fn (Get $get) => ($op = self::insumoDe($get)?->unidade_base->opcoesEntrada())
                                        ? array_key_first($op)
                                        : null)
                                    ->native(false)
                                    ->live()
                                    ->required(fn (Get $get) => filled($get('insumo_id')))
                                    /*
                                     * Precisa ser desidratado, senão não chega
                                     * em converter() e a conversão kg -> g nunca
                                     * acontece: tudo entra como grama e o custo
                                     * médio sai mil vezes errado.
                                     * Não vai para o banco porque converter()
                                     * remove a chave antes de salvar.
                                     */
                                    ->columnSpan(['default' => 1, 'md' => 1, 'lg' => 1, 'xl' => 2])
                                    ->validationMessages(['required' => 'Escolha a unidade.']),

                                CampoQuantidade::make('quantidade')
                                    ->label('Quantidade')
                                    ->maiorQueZero()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 1, 'lg' => 1, 'xl' => 2])
                                    ->validationMessages(['required' => 'Informe a quantidade.']),

                                CampoDinheiro::make('valor_total')
                                    ->label('Valor pago')
                                    ->maiorQueZero()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 1, 'lg' => 1, 'xl' => 2])
                                    ->validationMessages(['required' => 'Informe quanto foi pago neste item.']),

                                Placeholder::make('custo_convertido')
                                    ->label('Custo unitário')
                                    ->columnSpan(['default' => 1, 'md' => 2, 'lg' => 1, 'xl' => 2])
                                    ->content(function (Get $get) {
                                        $insumo = self::insumoDe($get);
                                        $quantidade = Decimal::deBr($get('quantidade'));
                                        $valor = Decimal::deBr($get('valor_total'));

                                        if (! $insumo || ! Decimal::positivo($quantidade)) {
                                            return '—';
                                        }

                                        $base = $insumo->unidade_base->paraBase(
                                            $quantidade,
                                            (string) ($get('unidade_entrada') ?? $insumo->unidade_base->sufixo())
                                        );

                                        return 'R$ '.Decimal::paraBr(Decimal::div($valor, $base), 4)
                                            .' / '.$insumo->unidade_base->sufixo();
                                    }),
                            ])
                            // converte para a unidade base ANTES de gravar
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::converter($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::converter($data))
                            // ao abrir para editar, mostra na unidade base
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data) {
                                $insumo = Insumo::find($data['insumo_id'] ?? null);
                                $data['unidade_entrada'] = $insumo?->unidade_base->sufixo();

                                return $data;
                            }),
                    ])
                    ->footer([
                        Text::make('Ao salvar, o custo médio ponderado de cada insumo é recalculado automaticamente.')
                            ->color('gray'),
                    ]),

                Section::make('Observações')
                    ->collapsed()
                    ->schema([
                        Textarea::make('observacoes')
                            ->hiddenLabel()
                            ->placeholder('Número da nota, condição de pagamento, o que for útil depois.')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
            ]);
    }

    /** Converte a quantidade digitada para a unidade base do insumo. */
    private static function converter(array $data): array
    {
        $insumo = Insumo::find($data['insumo_id'] ?? null);

        if ($insumo) {
            $data['quantidade'] = $insumo->unidade_base->paraBase(
                $data['quantidade'] ?? '0',
                (string) ($data['unidade_entrada'] ?? $insumo->unidade_base->sufixo()),
            );
        }

        unset($data['unidade_entrada'], $data['custo_convertido']);

        return $data;
    }

    private static function insumoDe(Get $get): ?Insumo
    {
        return Insumo::find($get('insumo_id'));
    }
}
