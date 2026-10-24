<?php

namespace App\Filament\Resources\Pedidos\Actions;

use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Models\Pedido;
use App\Services\FinalizarPedido;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;

/**
 * O fluxo da encomenda é só este: está a entregar ou foi entregue.
 * Sem passo de produção ou "pronto" no meio — eles marcam e seguem.
 *
 * Usado na listagem de pedidos e no widget de encomendas do dashboard.
 */
class AcoesDeEntrega
{
    public static function entregar(): Action
    {
        return Action::make('entregar')
            ->label('Marcar como entregue')
            ->icon('heroicon-m-truck')
            ->color('success')
            ->button()
            ->visible(fn (Pedido $record) => self::encomendaAberta($record))
            ->action(function (Pedido $record, FinalizarPedido $servico) {
                $pedido = $servico->mudarStatus($record, StatusPedido::Entregue);

                Notification::make()
                    ->title($pedido->identificacao.' entregue')
                    ->success()
                    ->send();
            });
    }

    /** Clicou em entregue sem querer, ou a entrega não aconteceu. */
    public static function desfazerEntrega(): Action
    {
        return Action::make('desfazerEntrega')
            ->label('Ainda não foi entregue')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (Pedido $record) => $record->tipo === TipoPedido::Encomenda
                && $record->status === StatusPedido::Entregue)
            ->requiresConfirmation()
            ->modalHeading('Voltar para "a entregar"')
            ->modalDescription('A encomenda volta para a lista de entregas pendentes. Estoque e custo não mudam.')
            ->modalSubmitActionLabel('Voltar para a entregar')
            ->action(function (Pedido $record, FinalizarPedido $servico) {
                $pedido = $servico->mudarStatus($record, StatusPedido::Confirmado);

                Notification::make()
                    ->title($pedido->identificacao.' voltou para a entregar')
                    ->warning()
                    ->send();
            });
    }

    public static function alterarEntrega(): Action
    {
        return Action::make('alterarEntrega')
            ->label('Alterar data de entrega')
            ->icon('heroicon-m-calendar-days')
            ->color('gray')
            ->visible(fn (Pedido $record) => $record->tipo === TipoPedido::Encomenda
                && $record->status !== StatusPedido::Cancelado)
            ->modalHeading('Alterar data de entrega')
            ->modalSubmitActionLabel('Salvar data')
            ->modalWidth('sm')
            ->fillForm(fn (Pedido $record) => ['data_entrega' => $record->data_entrega])
            ->schema(fn (Pedido $record) => [
                DatePicker::make('data_entrega')
                    ->label('Nova data de entrega')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate($record->data)
                    ->validationMessages([
                        'required' => 'Informe a data de entrega.',
                        'after_or_equal' => 'A entrega não pode ser antes do pedido.',
                    ]),
            ])
            // só a data muda: nada de estoque nem de custo envolvido
            ->action(function (Pedido $record, array $data) {
                $record->update(['data_entrega' => $data['data_entrega']]);

                Notification::make()
                    ->title('Entrega de '.$record->identificacao.' para '.$record->data_entrega->format('d/m/Y'))
                    ->success()
                    ->send();
            });
    }

    private static function encomendaAberta(Pedido $record): bool
    {
        return $record->tipo === TipoPedido::Encomenda && ! $record->status->finalizado();
    }
}
