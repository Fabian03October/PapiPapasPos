<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $service = ReportMetricsService::fromFilters($this->filters);
        $retention = $service->customerRetention();

        $shrinkage = $service->shrinkageValue();

        return [
            Stat::make('Valor de inventario', '$' . number_format($service->inventoryValue(), 2)),
            Stat::make('Mermas del periodo', '$' . number_format($shrinkage, 2))
                ->description($shrinkage > 0 ? 'Ver detalle en Conteos de inventario' : 'Sin mermas registradas')
                ->color($shrinkage > 0 ? 'danger' : 'success'),
            Stat::make('Clientes nuevos', $retention['new']),
            Stat::make('Clientes recurrentes', $retention['recurring']),
        ];
    }
}