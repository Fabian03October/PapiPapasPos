<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSummarySheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function array(): array
    {
        $totals = $this->service->totals();
        $retention = $this->service->customerRetention();

        return [
            ['Ventas totales', number_format($totals['net'], 2)],
            ['Transacciones', $totals['count']],
            ['Ticket promedio', number_format($totals['average_ticket'], 2)],
            ['Efectivo', number_format($totals['cash'], 2)],
            ['Tarjeta', number_format($totals['card'], 2)],
            ['Valor de inventario', number_format($this->service->inventoryValue(), 2)],
            ['Clientes nuevos', $retention['new']],
            ['Clientes recurrentes', $retention['recurring']],
        ];
    }

    public function headings(): array
    {
        return ['Métrica', 'Valor'];
    }

    public function title(): string
    {
        return 'Resumen';
    }
}