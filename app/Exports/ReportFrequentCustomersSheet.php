<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportFrequentCustomersSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function array(): array
    {
        return $this->service->frequentCustomers(15)
            ->map(fn ($row) => [$row->name, $row->visits, number_format($row->total, 2), $row->favorite_product ?? '—'])
            ->all();
    }

    public function headings(): array
    {
        return ['Cliente', 'Visitas', 'Total gastado', 'Pedido favorito'];
    }

    public function title(): string
    {
        return 'Clientes frecuentes';
    }
}
