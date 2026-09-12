<?php

namespace App\Filament\Resources\Ingredients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}