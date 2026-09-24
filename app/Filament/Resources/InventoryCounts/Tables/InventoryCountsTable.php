<?php

namespace App\Filament\Resources\InventoryCounts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryCountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'items as differences_count' => fn (Builder $q) => $q->where('difference', '!=', 0),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/M/Y H:i')
                    ->sortable(),
                TextColumn::make('cashSession.user.name')
                    ->label('Turno de'),
                TextColumn::make('user.name')
                    ->label('Contado por'),
                TextColumn::make('differences_count')
                    ->label('Diferencias')
                    ->badge()
                    ->formatStateUsing(fn (int $state) => $state > 0 ? $state . ' insumo(s)' : 'Sin diferencias')
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'success'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
