<?php

namespace App\Filament\Resources\Receitas\Pages;

use App\Filament\Resources\Receitas\ReceitaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReceita extends EditRecord
{
    protected static string $resource = ReceitaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
