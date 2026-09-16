<?php

namespace App\Filament\Resources\LoyaltyCards\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Schema; // Ajustado a tu namespace de Schema

class LoyaltyCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. Datos Generales del Nivel (La Tarjeta)
                Section::make('Configuración del Nivel')
                    ->description('Define el nombre del nivel y cuántas visitas se requieren para completarlo.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la Tarjeta / Nivel')
                            ->placeholder('Ej. Nivel 1 - Novato')
                            ->required(),

                        TextInput::make('level_number')
                            ->label('Orden del Nivel')
                            ->placeholder('Ej. 1, 2, 3...')
                            ->numeric()
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        TextInput::make('description')
                            ->label('Descripción para el cliente')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                // 2. Las "Ventanitas" de los Premios (Milestones)
                Section::make('Premios por Visitas')
                    ->description('Configura los regalos que se darán al llegar a cierta cantidad de visitas en este nivel.')
                    ->schema([
                        Repeater::make('milestones') 
                            ->relationship() // Asume relación 'milestones' en el modelo LoyaltyCard
                            ->label('')
                            ->addActionLabel('Agregar otro premio')
                            ->minItems(1)
                            ->maxItems(2)
                            ->schema([
                                TextInput::make('visits_required')
                                    ->label('Visitas requeridas')
                                    ->placeholder('Ej. 3 u 8')
                                    ->numeric()
                                    ->required(),

                                Select::make('reward_type')
                                    ->label('¿Qué premio se lleva?')
                                    ->options([
                                        'discount' => 'Descuento (%)',
                                        'free_product' => 'Producto Gratis',
                                    ])
                                    ->live()
                                    ->required(),

                                Select::make('discount_scope')
                                    ->label('¿A qué aplica el descuento?')
                                    ->options([
                                        'general' => 'A toda la compra',
                                        'specific' => 'A un producto específico',
                                    ])
                                    ->live()
                                    ->visible(fn (Get $get) => $get('reward_type') === 'discount'),

                                TextInput::make('discount_percentage')
                                    ->label('Porcentaje de descuento (%)')
                                    ->numeric()
                                    ->placeholder('Ej. 10')
                                    ->visible(fn (Get $get) => $get('reward_type') === 'discount'),

                                Select::make('product_id')
                                    ->label('Selecciona el Producto')
                                    ->relationship('product', 'name') 
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get) => 
                                        $get('reward_type') === 'free_product' || 
                                        ($get('reward_type') === 'discount' && $get('discount_scope') === 'specific')
                                    ),
                            ])->columns(3)
                    ])
            ]);
    }
}