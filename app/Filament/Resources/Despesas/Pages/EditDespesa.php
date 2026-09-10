<?php

namespace App\Filament\Resources\Despesas\Pages;

use App\Filament\Resources\Despesas\DespesaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDespesa extends EditRecord
{
    protected static string $resource = DespesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
