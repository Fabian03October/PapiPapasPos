<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PeakHoursChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Horas pico';

    protected function getData(): array
    {
        $peak = ReportMetricsService::fromFilters($this->filters)->peakHours();

        return [
            'datasets' => [
                ['label' => 'Ventas por hora', 'data' => $peak['data']],
            ],
            'labels' => $peak['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}