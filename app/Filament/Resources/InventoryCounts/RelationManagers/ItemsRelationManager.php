<?php

namespace App\Filament\Resources\InventoryCounts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Detalle por insumo';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ingredient.name')
            ->columns([
                TextColumn::make('ingredient.name')
                    ->label('Insumo'),
                TextColumn::make('expected_qty')
                    ->label('Esperado')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn ($record) => ' ' . $record->ingredient->unit),
                TextColumn::make('counted_qty')
                    ->label('Contado')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn ($record) => ' ' . $record->ingredient->unit),
                TextColumn::make('difference')
                    ->label('Diferencia')
                    ->numeric(decimalPlaces: 2)
                    ->formatStateUsing(fn (float $state, $record) => ($state > 0 ? '+' : '') . number_format($state, 2) . ' ' . $record->ingredient->unit)
                    ->color(fn (float $state) => match (true) {
                        $state < 0 => 'danger',
                        $state > 0 => 'info',
                        default => 'success',
                    }),
                TextColumn::make('impact')
                    ->label('Impacto')
                    ->state(fn ($record) => $record->difference * $record->ingredient->cost_per_unit)
                    ->money('MXN')
                    ->color(fn (float $state) => $state < 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('difference')
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ]);
    }
}
