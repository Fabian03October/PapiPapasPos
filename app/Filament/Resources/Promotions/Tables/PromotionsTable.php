<?php

namespace App\Filament\Resources\Promotions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'percent_off_sale' => 'Descuento %',
                        'fixed_price_combo' => 'Combo',
                        'fixed_price_single' => 'Precio especial',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'percent_off_sale' => 'info',
                        'fixed_price_combo' => 'warning',
                        'fixed_price_single' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('percent_value')
                    ->label('Descuento')
                    ->suffix('%')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('combo_price')
                    ->label('Precio fijo')
                    ->money('MXN')
                    ->placeholder('—')
                    ->sortable(),
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