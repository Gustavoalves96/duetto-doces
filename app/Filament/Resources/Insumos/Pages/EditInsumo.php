<?php

namespace App\Filament\Resources\Insumos\Pages;

use App\Filament\Resources\Insumos\InsumoResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Sem botão de excluir: apagar um insumo levaria junto o histórico de
 * movimentações. Para tirar da lista, desligue "Insumo em uso".
 */
class EditInsumo extends EditRecord
{
    protected static string $resource = InsumoResource::class;
}
