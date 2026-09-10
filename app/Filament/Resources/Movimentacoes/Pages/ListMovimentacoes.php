<?php

namespace App\Filament\Resources\Movimentacoes\Pages;

use App\Filament\Resources\Movimentacoes\MovimentacaoResource;
use Filament\Resources\Pages\ListRecords;

class ListMovimentacoes extends ListRecords
{
    protected static string $resource = MovimentacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
