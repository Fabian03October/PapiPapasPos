<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSalesTrendSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function array(): array
    {
        $trend = $this->service->salesTrend();

        return collect($trend['labels'])
            ->map(fn ($label, $i) => [$label, number_format($trend['data'][$i], 2)])
            ->all();
    }

    public function headings(): array
    {
        return ['Día', 'Ventas'];
    }

    public function title(): string
    {
        return 'Ventas por dia';
    }
}
