<?php

namespace App\Filament\Support;

use App\Models\Ingredient;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Receta con varios insumos de un jalón: se escogen varios (o "Todos") y
 * abajo sale un renglón por insumo para ponerle su cantidad.
 */
class IngredientRows
{
    public static function schema(): array
    {
        $rows = fn (array $ids) => collect($ids)->map(fn ($id) => ['ingredient_id' => $id, 'qty' => null])->values()->all();

        return [
            Select::make('pick')
                ->label('Insumos')
                ->multiple()
                ->searchable()
                ->options(fn () => Ingredient::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->live()
                ->afterStateUpdated(fn (?array $state, Set $set) => $set('items', $rows($state ?? [])))
                ->hintAction(Action::make('todos')->label('Seleccionar todos')
                    ->action(function (Set $set) use ($rows) {
                        $ids = Ingredient::where('is_active', true)->orderBy('name')->pluck('id')->all();
                        $set('pick', $ids);
                        $set('items', $rows($ids));
                    }))
                ->dehydrated(false),
            Repeater::make('items')
                ->label('Cantidad de cada uno')
                ->schema([
                    Select::make('ingredient_id')->label('Insumo')
                        ->options(fn () => Ingredient::where('is_active', true)->pluck('name', 'id'))
                        ->disabled()->dehydrated()->required(),
                    TextInput::make('qty')->label('Cantidad')->required()->numeric()->minValue(0.01)->step(0.01)
                        ->suffix(fn ($get) => Ingredient::find($get('ingredient_id'))?->unit),
                ])
                ->columns(2)
                ->addable(false)
                ->reorderable(false)
                ->minItems(1)
                ->helperText('En la misma unidad del insumo (ej. gramos, ml, piezas). Si el insumo ya estaba en la receta, se cambia su cantidad.'),
        ];
    }
}
