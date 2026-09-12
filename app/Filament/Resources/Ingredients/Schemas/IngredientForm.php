<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->placeholder('Ej. Papa, Salchicha, Domo')
                    ->required(),
                TextInput::make('unit')
                    ->label('Unidad')
                    ->placeholder('Ej. g, ml, pza')
                    ->required(),
                TextInput::make('stock_qty')
                    ->label('Stock actual')
                    ->numeric()
                    ->default(0),
                TextInput::make('min_stock')
                    ->label('Stock mínimo (alerta)')
                    ->numeric()
                    ->default(0)
                    ->helperText('Cuando el stock baje de este número, se mostrará una alerta.'),
                TextInput::make('cost_per_unit')
                    ->label('Costo por unidad')
                    ->numeric()
                    ->step(0.0001)
                    ->prefix('$')
                    ->default(0)
                    ->helperText('Ej. si compraste 2000g de papa por $500, el costo por gramo es $0.25'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}