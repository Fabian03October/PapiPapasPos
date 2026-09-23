<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportTopProductsSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function array(): array
    {
        $top = $this->service->topProducts(15);

        return collect($top['labels'])
            ->map(fn ($label, $i) => [$label, number_format($top['data'][$i], 2)])
            ->all();
    }

    public function headings(): array
    {
        return ['Producto', 'Ingresos'];
    }

    public function title(): string
    {
        return 'Top productos';
    }
}
