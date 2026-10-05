<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesHistoryStats extends BaseWidget
{
    protected static bool $isLazy = false;

    public string $range = 'today';

    public ?string $from = null;

    public ?string $until = null;

    protected function getStats(): array
    {
        $service = ReportMetricsService::fromFilters([
            'range' => $this->range,
            'from' => $this->from,
            'until' => $this->until,
        ]);

        $totals = $service->totals();
        $cancelled = $service->incidentStats('cancelacion');
        $replaced = $service->incidentStats('reposicion');

        return [
            Stat::make('Total efectivo', '$' . number_format($totals['cash'], 2)),
            Stat::make('Total tarjeta', '$' . number_format($totals['card'], 2)),
            Stat::make('Cancelados', $cancelled['count'] . ' · $' . number_format($cancelled['total'], 2))
                ->color($cancelled['count'] > 0 ? 'danger' : null),
            Stat::make('Repuestos', $replaced['count'] . ' · $' . number_format($replaced['total'], 2))
                ->color($replaced['count'] > 0 ? 'warning' : null),
        ];
    }
}
