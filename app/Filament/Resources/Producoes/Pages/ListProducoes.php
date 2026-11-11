<?php

namespace App\Filament\Resources\Producoes\Pages;

use App\Filament\Resources\Producoes\ProducaoResource;
use Filament\Resources\Pages\ListRecords;

class ListProducoes extends ListRecords
{
    protected static string $resource = ProducaoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
