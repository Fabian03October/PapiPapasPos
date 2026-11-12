<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Orden de aparición')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
                Toggle::make('gives_stamp')
                    ->label('Da sello de la tarjeta')
                    ->helperText('La venta suma sello solo si trae algo de una categoría con esto encendido (ej. Papas, Órdenes; no Bebidas).')
                    ->default(true),
            ]);
    }
}