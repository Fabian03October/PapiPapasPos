<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TopProductsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Productos más vendidos';

    protected function getData(): array
    {
        $top = ReportMetricsService::fromFilters($this->filters)->topProducts(8);

        return [
            'datasets' => [
                ['label' => 'Ingresos', 'data' => $top['data']],
            ],
            'labels' => $top['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}