<?php

namespace App\Filament\Resources\Receitas;

use App\Filament\Resources\Receitas\Pages\CreateReceita;
use App\Filament\Resources\Receitas\Pages\EditReceita;
use App\Filament\Resources\Receitas\Pages\ListReceitas;
use App\Filament\Resources\Receitas\Schemas\ReceitaForm;
use App\Filament\Resources\Receitas\Tables\ReceitasTable;
use App\Models\Receita;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReceitaResource extends Resource
{
    protected static ?string $model = Receita::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'ficha técnica';

    protected static ?string $pluralModelLabel = 'fichas técnicas';

    public static function form(Schema $schema): Schema
    {
        return ReceitaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceitasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceitas::route('/'),
            'create' => CreateReceita::route('/create'),
            'edit' => EditReceita::route('/{record}/edit'),
        ];
    }
}
