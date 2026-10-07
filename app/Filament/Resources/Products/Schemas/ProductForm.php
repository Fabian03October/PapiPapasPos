<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del producto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required(),
                        Select::make('category_id')
                            ->label('Categoría')
                            ->options(Category::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
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
                        FileUpload::make('image_path')
                            ->label('Foto')
                            ->helperText('Aparece en el botón del producto en la pantalla de venta. PNG, JPG o WEBP. Máximo 2MB.')
                            // Sin ->image() por la misma razón que en
                            // WalletCardDesign: en Railway la detección del
                            // tipo real del archivo rechaza imágenes genuinas.
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),

                Section::make('Modificadores')
                    ->schema([
                        Select::make('modifierGroups')
                            ->label('Grupos de modificadores')
                            ->relationship('modifierGroups', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Selecciona qué grupos (aderezos, salsas, etc.) aplican a este producto.'),
                    ]),
            ]);
    }
}