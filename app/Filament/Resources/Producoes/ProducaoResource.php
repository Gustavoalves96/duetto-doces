<?php

namespace App\Filament\Resources\Producoes;

use App\Filament\Resources\Producoes\Pages\ListProducoes;
use App\Filament\Resources\Producoes\Tables\ProducoesTable;
use App\Models\Producao;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Histórico das fornadas. Produção nasce da ação "Produzir" na ficha técnica;
 * aqui só dá para consultar e desfazer um lançamento errado.
 */
class ProducaoResource extends Resource
{
    protected static ?string $model = Producao::class;

    // sem isso o Filament pluraliza em inglês: /producaos
    protected static ?string $slug = 'producoes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'produção';

    protected static ?string $pluralModelLabel = 'produções';

    public static function table(Table $table): Table
    {
        return ProducoesTable::configure($table);
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
            'index' => ListProducoes::route('/'),
        ];
    }
}
