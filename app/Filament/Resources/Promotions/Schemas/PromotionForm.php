<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Models\Product;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Información general')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Ej. Martes de 2x1, Papas a $30')
                            ->required()
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label('Tipo de promoción')
                            ->options([
                                'percent_off_sale' => 'Descuento por porcentaje',
                                'fixed_price_combo' => 'Combo a precio fijo (2 o más productos)',
                                'fixed_price_single' => 'Precio especial de un solo producto',
                            ])
                            ->required()
                            ->live()
                            ->columnSpanFull()
                            ->helperText('Descuento %: aplica a toda la venta o a productos específicos. Combo: precio fijo para 2+ productos. Precio especial: un solo producto cambia de precio temporalmente.'),

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

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ]),

                Section::make('Productos')
                    ->description('Qué productos forman parte de esta promoción.')
                    ->schema([
                        Repeater::make('products')
                            ->label('')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto')
                                    ->options(fn () => Product::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable(),
                                TextInput::make('qty_required')
                                    ->label('Cantidad requerida')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Agregar producto')
                            ->defaultItems(0)
                            ->helperText(fn (Get $get) => $get('type') === 'percent_off_sale'
                                ? 'Opcional: si no agregas ninguno, el descuento aplica a toda la venta.'
                                : 'Agrega cada producto que forme parte de esta promoción, con la cantidad necesaria.'),
                    ]),

                Section::make('Vigencia')
                    ->description('Cuándo está activa esta promoción. Deja en blanco lo que no quieras restringir.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('valid_from')
                            ->label('Válida desde'),
                        DatePicker::make('valid_until')
                            ->label('Válida hasta'),

                        TimePicker::make('start_time')
                            ->label('Hora de inicio')
                            ->seconds(false),
                        TimePicker::make('end_time')
                            ->label('Hora de fin')
                            ->seconds(false),

                        CheckboxList::make('days_of_week')
                            ->label('Días de la semana')
                            ->options([
                                1 => 'Lun',
                                2 => 'Mart',
                                3 => 'Miér',
                                4 => 'Juev',
                                5 => 'Vier',
                                6 => 'Sáb',
                                0 => 'Dom',
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->helperText('Deja todas sin marcar para que aplique todos los días.'),
                    ]),

            ]);
    }
}