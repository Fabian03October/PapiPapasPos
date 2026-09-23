<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $totals = ReportMetricsService::fromFilters($this->filters)->totals();

        return [
            Stat::make('Ventas totales', '$' . number_format($totals['net'], 2)),
            Stat::make('Transacciones', $totals['count']),
            Stat::make('Ticket promedio', '$' . number_format($totals['average_ticket'], 2)),
            Stat::make('Efectivo', '$' . number_format($totals['cash'], 2)),
            Stat::make('Tarjeta', '$' . number_format($totals['card'], 2)),
        ];
    }
}