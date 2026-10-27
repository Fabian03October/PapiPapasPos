<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ingresos, gastos (de caja y generales) y ganancia del periodo del filtro. */
class FinanceStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Ingresos, gastos y ganancia';

    protected function getStats(): array
    {
        $f = ReportMetricsService::fromFilters($this->filters ?? [])->finances();
        $money = fn (float $v) => '$'.number_format($v, 2);

        return [
            Stat::make('Ingresos', $money($f['ingresos']))
                ->description('Caja '.$money($f['caja']['ingresos']).' · Generales '.$money($f['general']['ingresos'])),
            Stat::make('Gastos', $money($f['gastos']))
                ->description('Caja '.$money($f['caja']['gastos']).' · Generales '.$money($f['general']['gastos']))
                ->color('danger'),
            Stat::make('Ganancia', $money($f['ganancia']))
                ->description('Ventas '.$money($f['ventas']).' − gastos')
                ->color($f['ganancia'] >= 0 ? 'success' : 'danger'),
        ];
    }
}
