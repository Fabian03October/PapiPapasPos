<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class FrequentCustomersTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Clientes frecuentes';
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $service = ReportMetricsService::fromFilters($this->filters);

        return $table
            ->query($service->frequentCustomersQuery())
            ->columns([
                TextColumn::make('name')->label('Cliente'),
                TextColumn::make('visits')->label('Visitas'),
                TextColumn::make('total')->label('Total gastado')->money('MXN'),
                TextColumn::make('favorite_product')->label('Pedido favorito')->placeholder('—'),
            ])
            ->paginated(false);
    }
}
