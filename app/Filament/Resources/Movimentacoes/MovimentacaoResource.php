<?php

namespace App\Filament\Resources\Movimentacoes;

use App\Filament\Resources\Movimentacoes\Pages\ListMovimentacoes;
use App\Filament\Resources\Movimentacoes\Tables\MovimentacoesTable;
use App\Models\Movimentacao;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Livro-razão do estoque. Só leitura: toda linha aqui nasceu de uma compra,
 * produção, venda, perda ou ajuste. Editar uma movimentação à mão faria o
 * saldo do insumo divergir do histórico que o explica.
 */
class MovimentacaoResource extends Resource
{
    protected static ?string $model = Movimentacao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'movimentação';

    protected static ?string $pluralModelLabel = 'movimentações';

    public static function table(Table $table): Table
    {
        return MovimentacoesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMovimentacoes::route('/'),
        ];
    }
}
