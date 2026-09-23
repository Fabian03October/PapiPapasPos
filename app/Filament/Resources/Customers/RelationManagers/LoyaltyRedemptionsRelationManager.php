<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoyaltyRedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'loyaltyRedemptions';

    protected static ?string $title = 'Historial de canjes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            //
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('loyaltyLevel.level_number')
                    ->label('Nivel'),
                TextColumn::make('type')
                    ->label('Premio')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'discount' => 'Descuento',
                        'gift' => 'Producto gratis',
                        default => $state,
                    })
                    ->color(fn (string $state) => $state === 'discount' ? 'success' : 'info'),
                TextColumn::make('discount_applied')
                    ->label('Descuento aplicado')
                    ->money('MXN')
                    ->placeholder('—'),
                TextColumn::make('redeemed_at')
                    ->label('Fecha')
                    ->dateTime('d/M/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('redeemed_at', 'desc')
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ]);
    }
}