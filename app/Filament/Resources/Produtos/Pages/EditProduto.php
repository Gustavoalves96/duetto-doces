<?php

namespace App\Filament\Resources\Produtos\Pages;

use App\Filament\Resources\Produtos\ProdutoResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Sem botão de excluir: apagar um produto levaria junto fichas técnicas e o
 * histórico de movimentações. Para tirar da lista, desligue "Produto à venda".
 */
class EditProduto extends EditRecord
{
    protected static string $resource = ProdutoResource::class;
}
