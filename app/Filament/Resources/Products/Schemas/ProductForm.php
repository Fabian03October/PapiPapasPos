<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Categoría')
                    ->options(Category::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU'),
                TextInput::make('base_price')
                    ->label('Precio base')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                Toggle::make('prints_to_kitchen')
                    ->label('Imprime en cocina')
                    ->default(true),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}