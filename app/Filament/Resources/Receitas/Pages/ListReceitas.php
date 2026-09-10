<?php

namespace App\Filament\Resources\Receitas\Pages;

use App\Filament\Resources\Receitas\ReceitaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceitas extends ListRecords
{
    protected static string $resource = ReceitaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
