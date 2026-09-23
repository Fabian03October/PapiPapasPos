<?php

namespace App\Filament\Widgets;

use App\Models\Ingredient;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockTable extends BaseWidget
{
    protected static ?string $heading = 'Alertas de stock bajo';
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ingredient::query()
                    ->where('is_active', true)
                    ->whereColumn('stock_qty', '<=', 'min_stock')
                    ->orderBy('stock_qty')
            )
            ->columns([
                TextColumn::make('name')->label('Insumo'),
                TextColumn::make('stock_qty')->label('Stock actual'),
                TextColumn::make('min_stock')->label('Mínimo'),
                TextColumn::make('unit')->label('Unidad'),
            ])
            ->paginated(false);
    }
}