<?php

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Services\RegistrarCompra;
use App\Support\Decimal;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Excluir compra')
                // apagar sem estornar deixaria o estoque mentindo
                ->before(fn () => app(RegistrarCompra::class)->estornar($this->getRecord())),
        ];
    }

    /**
     * Se a compra estava estornada, salvar volta a aplicá-la.
     * Compra já aplicada tem o repeater bloqueado, então aqui só mudou
     * fornecedor, data ou observação — e aplicar() é idempotente.
     */
    protected function afterSave(): void
    {
        $compra = app(RegistrarCompra::class)->aplicar($this->getRecord());

        Notification::make()
            ->title('Compra salva')
            ->body('Total de '.Decimal::paraReal($compra->total).'.')
            ->success()
            ->send();
    }
}
