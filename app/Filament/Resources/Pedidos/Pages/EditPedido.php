<?php

namespace App\Filament\Resources\Pedidos\Pages;

use App\Filament\Resources\Pedidos\PedidoResource;
use App\Services\FinalizarPedido;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPedido extends EditRecord
{
    protected static string $resource = PedidoResource::class;

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
     * Um pedido já efetivado tem estoque baixado e custo congelado. Se os itens
     * mudaram, estornamos e efetivamos de novo para o estoque bater — o custo
     * é recongelado com o valor de agora, que é o certo, já que a venda mudou.
     */
    protected function afterSave(): void
    {
        $servico = app(FinalizarPedido::class);
        $pedido = $this->getRecord();

        if ($pedido->jaBaixouEstoque()) {
            $servico->estornar($pedido);
        }

        $servico->sincronizar($pedido);
    }
}
