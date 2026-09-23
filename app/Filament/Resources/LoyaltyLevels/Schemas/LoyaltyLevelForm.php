<?php

namespace App\Filament\Resources\LoyaltyLevels\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LoyaltyLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nivel')
                    ->description('Todos los clientes suben por estos niveles en el mismo orden. En la visita 4 se aplica el % de descuento; en la visita 8 se entrega el producto gratis y el cliente sube de nivel.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('level_number')
                            ->label('Número de nivel')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull()
                            ->helperText('Ej. 1, 2, 3... El nivel más bajo es el que empiezan todos los clientes nuevos.'),

                        TextInput::make('discount_percent')
                            ->label('Descuento en la visita 4')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('discount_description')
                            ->label('Descripción del descuento')
                            ->placeholder('Ej. 10% en toda tu cuenta')
                            ->helperText('Así se le muestra al cajero/cliente.'),

                        Select::make('free_product_id')
                            ->label('Producto gratis en la visita 8')
                            ->options(fn () => Product::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('gift_description')
                            ->label('Descripción del regalo')
                            ->placeholder('Ej. Orden grande de salchipapas gratis')
                            ->helperText('Así se le muestra al cajero/cliente.'),
                    ]),
            ]);
    }
}