<?php

namespace App\Filament\Resources\ModifierRecipeItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModifierRecipeItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('modifier.group.name')
                    ->label('Grupo'),
                TextColumn::make('modifier.name')
                    ->label('Modificador'),
                TextColumn::make('ingredient.name')
                    ->label('Insumo'),
                TextColumn::make('qty')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2),
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