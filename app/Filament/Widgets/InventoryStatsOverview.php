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

        return [
            Stat::make('Valor de inventario', '$' . number_format($service->inventoryValue(), 2)),
            Stat::make('Clientes nuevos', $retention['new']),
            Stat::make('Clientes recurrentes', $retention['recurring']),
        ];
    }
}