<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\SaleItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DeadStockTable extends BaseWidget
{
    protected static ?string $heading = 'Productos sin movimiento (14 días)';
        protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $activeProductIds = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.created_at', '>=', now()->subDays(14))
            ->pluck('sale_items.product_id');

        return $table
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->whereNotIn('id', $activeProductIds)
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')->label('Producto'),
                TextColumn::make('base_price')->label('Precio')->money('MXN'),
            ])
            ->paginated(false);
    }
}