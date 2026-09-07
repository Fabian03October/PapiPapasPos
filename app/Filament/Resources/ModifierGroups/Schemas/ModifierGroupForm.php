<?php

namespace App\Filament\Resources\ModifierGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ModifierGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->placeholder('Ej. Aderezos, Salsas, Extras')
                    ->required(),
                TextInput::make('min_select')
                    ->label('Mínimo a elegir')
                    ->helperText('0 = opcional, 1 o más = obligatorio elegir al menos ese número')
                    ->numeric()
                    ->default(0),
                TextInput::make('max_select')
                    ->label('Máximo a elegir')
                    ->helperText('Ej. 1 para selección única (como tamaño), 3 para hasta 3 toppings')
                    ->numeric()
                    ->default(1),
                Toggle::make('is_required')
                    ->label('Obligatorio')
                    ->default(false),
                TextInput::make('sort_order')
                    ->label('Orden de aparición')
                    ->helperText('Los grupos con número menor aparecen primero en el popup')
                    ->numeric()
                    ->default(0),
            ]);
    }
}