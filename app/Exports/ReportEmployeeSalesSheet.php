<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportEmployeeSalesSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function collection()
    {
        return $this->service->salesByEmployee()->map(fn ($row) => [
            $row->employee, $row->tickets, number_format($row->total, 2),
        ]);
    }

    public function headings(): array
    {
        return ['Cajero', 'Tickets', 'Total vendido'];
    }

    public function title(): string
    {
        return 'Ventas por empleado';
    }
}