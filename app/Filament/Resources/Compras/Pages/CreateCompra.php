<?php

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Services\RegistrarCompra;
use App\Support\Decimal;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * O Repeater já gravou os itens neste ponto. Só agora o serviço tem o que
     * somar, então é aqui que o custo médio é recalculado.
     */
    protected function afterCreate(): void
    {
        $compra = app(RegistrarCompra::class)->aplicar($this->getRecord());

        Notification::make()
            ->title('Compra registrada')
            ->body('Total de '.Decimal::paraReal($compra->total).'. Custo médio dos insumos atualizado.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
