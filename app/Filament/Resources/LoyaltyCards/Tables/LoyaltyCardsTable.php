<?php

namespace App\Filament\Resources\LoyaltyCards\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoyaltyCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('milestones_count')
                    ->label('Premios')
                    ->counts('milestones'),
                TextColumn::make('reset_behavior')
                    ->label('Al completar')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'reinicia' => 'Se reinicia',
                        'no_reinicia' => 'Sigue acumulando',
                        'nueva_tarjeta' => 'Nueva tarjeta',
                        default => $state,
                    }),
                TextColumn::make('valid_from')
                    ->label('Desde')
                    ->date('d/M/Y')
                    ->placeholder('Sin límite')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label('Hasta')
                    ->date('d/M/Y')
                    ->placeholder('Sin límite')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
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