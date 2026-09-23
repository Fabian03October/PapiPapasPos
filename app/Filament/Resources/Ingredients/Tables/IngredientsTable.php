<?php

namespace App\Filament\Resources\Ingredients\Tables;

use App\Models\InventoryMovement;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IngredientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Unidad')
                    ->searchable(),
                TextColumn::make('stock_qty')
                    ->label('Stock actual')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn ($record) => $record->stock_qty <= $record->min_stock ? 'danger' : null)
                    ->weight(fn ($record) => $record->stock_qty <= $record->min_stock ? 'bold' : null),
                TextColumn::make('min_stock')
                    ->label('Mínimo')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('cost_per_unit')
                    ->label('Costo/unidad')
                    ->money('MXN', divideBy: 1)
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('registrarEntrada')
                    ->label('Registrar entrada')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('success')
                    ->schema([
                        TextInput::make('qty')
                            ->label('Cantidad recibida')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        TextInput::make('unit_cost')
                            ->label('Costo por unidad')
                            ->numeric()
                            ->minValue(0)
                            ->default(fn ($record) => $record->cost_per_unit),
                    ])
                    ->action(function (array $data, $record) {
                        InventoryMovement::create([
                            'ingredient_id' => $record->id,
                            'type' => 'compra',
                            'qty' => $data['qty'],
                            'unit_cost' => $data['unit_cost'],
                            'user_id' => auth()->id(),
                        ]);

                        $record->increment('stock_qty', $data['qty']);

                        if (filled($data['unit_cost'])) {
                            $record->update(['cost_per_unit' => $data['unit_cost']]);
                        }

                        Notification::make()->title('Entrada registrada')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}