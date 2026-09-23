<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements WithMultipleSheets
{
    public function __construct(protected ReportMetricsService $service) {}

    public function sheets(): array
    {
        return [
            new ReportSummarySheet($this->service),
            new ReportSalesTrendSheet($this->service),
            new ReportTopProductsSheet($this->service),
            new ReportLowStockSheet($this->service),
            new ReportEmployeeSalesSheet($this->service),
            new ReportFrequentCustomersSheet($this->service),
        ];
    }
}