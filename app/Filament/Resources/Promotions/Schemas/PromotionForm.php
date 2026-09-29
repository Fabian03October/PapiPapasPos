<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Models\Modifier;
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
                            ->placeholder('Ej. Martes de 2x1, Papas a $30, Salchicha extra gratis')
                            ->required()
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label('Tipo de promoción')
                            ->options([
                                'free' => 'Producto o extra gratis',
                                'discount' => 'Precio fijo o descuento',
                            ])
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Select::make('scope')
                            ->label('¿A qué se aplica?')
                            ->options([
                                'product' => 'A un producto',
                                'modifier' => 'A un extra / modificador (ej. "salchicha extra")',
                            ])
                            ->required()
                            ->live()
                            ->columnSpanFull()
                            ->helperText('Elige uno — nunca se mezclan. Si tu promoción es sobre un extra específico (como "salchicha extra gratis"), elige "extra/modificador", no "producto".'),

                        Select::make('discount_mode')
                            ->label('¿Cómo se calcula?')
                            ->options([
                                'percent' => 'Porcentaje de descuento',
                                'fixed_price' => 'Precio fijo',
                            ])
                            ->visible(fn (Get $get) => $get('type') === 'discount')
                            ->required(fn (Get $get) => $get('type') === 'discount')
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('percent_value')
                            ->label('Porcentaje de descuento')
                            ->numeric()
                            ->suffix('%')
                            ->visible(fn (Get $get) => $get('type') === 'discount' && $get('discount_mode') === 'percent')
                            ->required(fn (Get $get) => $get('type') === 'discount' && $get('discount_mode') === 'percent'),

                        TextInput::make('combo_price')
                            ->label('Precio fijo')
                            ->numeric()
                            ->prefix('$')
                            ->visible(fn (Get $get) => $get('type') === 'discount' && $get('discount_mode') === 'fixed_price')
                            ->required(fn (Get $get) => $get('type') === 'discount' && $get('discount_mode') === 'fixed_price')
                            ->helperText(fn (Get $get) => $get('scope') === 'modifier'
                                ? 'El nuevo precio de ese extra mientras la promoción esté activa.'
                                : 'El precio fijo total del producto (o combo, si agregas más de uno abajo).'),

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ]),

                Section::make('Producto')
                    ->description('El producto al que se le aplica esta promoción.')
                    ->visible(fn (Get $get) => $get('scope') === 'product')
                    ->schema([
                        Repeater::make('products')
                            ->hiddenLabel()
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
                            ->helperText('Agrega 1 producto para "gratis" o "precio especial" de un solo producto, o 2+ para un combo a precio fijo. Déjalo vacío para que el descuento aplique a toda la venta (solo válido con "Porcentaje de descuento").'),
                    ]),

                Section::make('Extra / modificador')
                    ->description('El extra exacto al que se le aplica esta promoción (ej. "salchicha extra"), sin importar en qué producto se agregue.')
                    ->visible(fn (Get $get) => $get('scope') === 'modifier')
                    ->schema([
                        Repeater::make('modifiers')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('modifier_id')
                                    ->label('Extra / modificador')
                                    ->options(fn () => Modifier::with('group')->get()
                                        ->mapWithKeys(fn (Modifier $m) => [$m->id => ($m->group->name ?? '') . ' — ' . $m->name]))
                                    ->required()
                                    ->searchable(),
                            ])
                            ->addActionLabel('Agregar extra')
                            ->defaultItems(0)
                            ->minItems(1),

                        Repeater::make('products')
                            ->label('Restringir a estos productos (opcional)')
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
                            ->helperText('Déjalo vacío para que el extra aplique sin importar en qué producto se agregue. Llénalo solo si quieres, por ejemplo, "el extra gratis solo si se agrega a la salchipapa".'),
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
