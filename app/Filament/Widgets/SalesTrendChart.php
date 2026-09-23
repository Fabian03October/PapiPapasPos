<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tendencia de ventas';

    protected function getData(): array
    {
        $trend = ReportMetricsService::fromFilters($this->filters)->salesTrend();

        return [
            'datasets' => [
                ['label' => 'Ventas', 'data' => $trend['data']],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}