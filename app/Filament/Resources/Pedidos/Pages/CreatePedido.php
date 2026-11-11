<?php

namespace App\Filament\Resources\Pedidos\Pages;

use App\Enums\StatusPedido;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Services\FinalizarPedido;
use App\Support\Decimal;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePedido extends CreateRecord
{
    protected static string $resource = PedidoResource::class;

    /**
     * Os itens já existem aqui. Uma venda avulsa nasce entregue e congela o
     * custo na hora; encomenda nasce a entregar, reserva o produto e só
     * congela o custo quando for marcada como entregue na listagem.
     */
    protected function afterCreate(): void
    {
        $pedido = app(FinalizarPedido::class)->sincronizar($this->getRecord());

        Notification::make()
            ->title('Pedido '.$pedido->identificacao.' salvo')
            ->body(match (true) {
                ! $pedido->jaBaixouEstoque() => 'Orçamento registrado. O estoque só é baixado ao confirmar.',
                $pedido->status !== StatusPedido::Entregue => 'Produto reservado. O custo é congelado na entrega.',
                $pedido->temItemSemCusto() => 'Estoque baixado, mas algum produto ainda não tem custo: lance a produção.',
                default => 'Custo congelado em '.Decimal::paraReal($pedido->cmv()).' e estoque baixado.',
            })
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
