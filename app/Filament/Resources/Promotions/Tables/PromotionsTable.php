<?php

namespace App\Filament\Resources\Promotions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
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
                        'free' => 'Gratis',
                        'discount' => 'Precio fijo o descuento',
                        default => $state,
                    })
                    ->color(fn (string $state) => $state === 'free' ? 'success' : 'info'),
                TextColumn::make('scope')
                    ->label('Alcance')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'product' => 'Producto',
                        'modifier' => 'Extra/modificador',
                        default => $state,
                    })
                    ->color('gray'),
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
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->placeholder('Todas')
                    ->default(true),
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
