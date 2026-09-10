<?php

namespace App\Filament\Resources\Clientes\Schemas;

use App\Filament\Forms\Components\CampoTelefone;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->placeholder('Maria Silva')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages(['required' => 'O nome do cliente é obrigatório.']),

                        CampoTelefone::make('telefone')
                            ->label('WhatsApp / telefone')
                            ->helperText('Com DDD. Usado para combinar a entrega.'),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->placeholder('Não come nozes. Prefere entrega depois das 18h.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
