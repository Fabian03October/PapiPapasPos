<?php

namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->placeholder('Ej. Martes de 2x1, Papas a $30')
                    ->required(),

                Select::make('type')
                    ->label('Tipo de promoción')
                    ->options([
                        'percent_off_sale' => 'Descuento por porcentaje',
                        'fixed_price_combo' => 'Combo a precio fijo (2 o más productos)',
                        'fixed_price_single' => 'Precio especial de un solo producto',
                    ])
                    ->required()
                    ->live()
                    ->helperText('Descuento %: aplica a toda la venta o a productos específicos. Combo: precio fijo para una combinación de 2+ productos. Precio especial: un solo producto cambia de precio temporalmente.'),

                TextInput::make('percent_value')
                    ->label('Porcentaje de descuento')
                    ->numeric()
                    ->suffix('%')
                    ->visible(fn (Get $get) => $get('type') === 'percent_off_sale')
                    ->required(fn (Get $get) => $get('type') === 'percent_off_sale'),

                TextInput::make('combo_price')
                    ->label('Precio fijo')
                    ->numeric()
                    ->prefix('$')
                    ->visible(fn (Get $get) => in_array($get('type'), ['fixed_price_combo', 'fixed_price_single']))
                    ->required(fn (Get $get) => in_array($get('type'), ['fixed_price_combo', 'fixed_price_single']))
                    ->helperText(fn (Get $get) => $get('type') === 'fixed_price_single'
                        ? 'El nuevo precio de ese producto mientras la promoción esté activa.'
                        : 'El precio fijo del combo completo, sin importar la suma de sus partes.'),

                DatePicker::make('valid_from')
                    ->label('Válida desde (opcional)')
                    ->helperText('Déjalo vacío para que no tenga fecha de inicio.'),
                DatePicker::make('valid_until')
                    ->label('Válida hasta (opcional)')
                    ->helperText('Déjalo vacío para que no tenga fecha de fin.'),

                CheckboxList::make('days_of_week')
                    ->label('Días de la semana (opcional)')
                    ->options([
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                        0 => 'Domingo',
                    ])
                    ->columns(4)
                    ->helperText('Deja todas sin marcar para que aplique todos los días.'),

                TimePicker::make('start_time')
                    ->label('Hora de inicio (opcional)')
                    ->seconds(false)
                    ->helperText('Déjalo vacío para que aplique desde que abre el negocio.'),
                TimePicker::make('end_time')
                    ->label('Hora de fin (opcional)')
                    ->seconds(false)
                    ->helperText('Déjalo vacío para que no tenga hora límite.'),

                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}