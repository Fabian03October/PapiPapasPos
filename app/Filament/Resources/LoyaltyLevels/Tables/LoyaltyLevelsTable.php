<?php

namespace App\Filament\Resources\LoyaltyLevels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoyaltyLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level_number')
                    ->label('Nivel')
                    ->sortable(),
                TextColumn::make('discount_description')
                    ->label('Descuento (visita 4)')
                    ->description(fn (LoyaltyLevel|\App\Models\LoyaltyLevel $record) => $record->discount_percent . '%')
                    ->placeholder('Sin descripción'),
                TextColumn::make('gift_description')
                    ->label('Regalo (visita 8)')
                    ->description(fn (\App\Models\LoyaltyLevel $record) => $record->freeProduct?->name)
                    ->placeholder('Sin descripción'),
            ])
            ->defaultSort('level_number')
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