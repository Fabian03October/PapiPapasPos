<?php

namespace App\Filament\Resources\ModifierRecipeItems\Schemas;

use App\Models\Ingredient;
use App\Models\Modifier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ModifierRecipeItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('modifier_id')
                    ->label('Modificador')
                    ->options(Modifier::with('group')->get()->mapWithKeys(
                        fn ($m) => [$m->id => $m->group->name . ' → ' . $m->name]
                    ))
                    ->required()
                    ->searchable(),
                Select::make('ingredient_id')
                    ->label('Insumo')
                    ->options(Ingredient::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('qty')
                    ->label('Cantidad')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->helperText('En la misma unidad del insumo (ej. gramos, ml, piezas).'),
            ]);
    }
}