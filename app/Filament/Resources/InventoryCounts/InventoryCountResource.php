<?php

namespace App\Filament\Resources\InventoryCounts;

use App\Filament\Resources\InventoryCounts\Pages\ListInventoryCounts;
use App\Filament\Resources\InventoryCounts\Pages\ViewInventoryCount;
use App\Filament\Resources\InventoryCounts\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\InventoryCounts\Tables\InventoryCountsTable;
use App\Models\InventoryCount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryCountResource extends Resource
{
    protected static ?string $model = InventoryCount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Conteos de inventario';
    protected static ?string $modelLabel = 'conteo de inventario';
    protected static ?string $pluralModelLabel = 'conteos de inventario';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return InventoryCountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryCounts::route('/'),
            'view' => ViewInventoryCount::route('/{record}'),
        ];
    }
}
