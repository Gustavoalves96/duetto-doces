<?php

namespace App\Filament\Resources\Pedidos\Pages;

use App\Filament\Resources\Pedidos\PedidoResource;
use App\Services\FinalizarPedido;
use App\Support\Decimal;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePedido extends CreateRecord
{
    protected static string $resource = PedidoResource::class;

    /**
     * Os itens já existem aqui. Uma venda avulsa nasce entregue e é efetivada
     * na hora; encomenda nasce como orçamento e só congela o custo quando for
     * confirmada pela ação da listagem.
     */
    protected function afterCreate(): void
    {
        $pedido = app(FinalizarPedido::class)->sincronizar($this->getRecord());

        Notification::make()
            ->title('Pedido '.$pedido->identificacao.' salvo')
            ->body($pedido->jaBaixouEstoque()
                ? 'Custo congelado em '.Decimal::paraReal($pedido->cmv()).' e estoque baixado.'
                : 'Orçamento registrado. O estoque só é baixado ao confirmar.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
