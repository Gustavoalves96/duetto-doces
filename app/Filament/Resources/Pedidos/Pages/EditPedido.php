<?php

namespace App\Filament\Resources\Pedidos\Pages;

use App\Filament\Resources\Pedidos\PedidoResource;
use App\Services\FinalizarPedido;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPedido extends EditRecord
{
    protected static string $resource = PedidoResource::class;

    // estorno, itens novos e nova baixa valem juntos ou não valem
    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Excluir pedido')
                // devolve o produto ao estoque antes de sumir com o registro
                ->before(fn () => app(FinalizarPedido::class)->estornar($this->getRecord())),
        ];
    }

    /**
     * Roda antes de o Repeater gravar: os itens no banco ainda são os que
     * foram baixados, e é exatamente isso que precisa voltar para a prateleira.
     * No afterSave já seria tarde — o estorno devolveria as quantidades novas.
     */
    protected function beforeSave(): void
    {
        app(FinalizarPedido::class)->estornar($this->getRecord());
    }

    /** Baixa de novo, agora com os itens como ficaram. */
    protected function afterSave(): void
    {
        app(FinalizarPedido::class)->sincronizar($this->getRecord());
    }
}
