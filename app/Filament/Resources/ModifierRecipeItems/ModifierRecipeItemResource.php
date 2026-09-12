<?php

namespace App\Filament\Resources\ModifierRecipeItems;

use App\Filament\Resources\ModifierRecipeItems\Pages\CreateModifierRecipeItem;
use App\Filament\Resources\ModifierRecipeItems\Pages\EditModifierRecipeItem;
use App\Filament\Resources\ModifierRecipeItems\Pages\ListModifierRecipeItems;
use App\Filament\Resources\ModifierRecipeItems\Schemas\ModifierRecipeItemForm;
use App\Filament\Resources\ModifierRecipeItems\Tables\ModifierRecipeItemsTable;
use App\Models\ModifierRecipeItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModifierRecipeItemResource extends Resource
{
    protected static ?string $model = ModifierRecipeItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';
    protected static ?string $navigationLabel = 'Recetas de modificadores';
    protected static ?string $modelLabel = 'receta';
    protected static ?string $pluralModelLabel = 'recetas';

    public static function form(Schema $schema): Schema
    {
        return ModifierRecipeItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModifierRecipeItemsTable::configure($table);
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
            'index' => ListModifierRecipeItems::route('/'),
            'create' => CreateModifierRecipeItem::route('/create'),
            'edit' => EditModifierRecipeItem::route('/{record}/edit'),
        ];
    }
}
