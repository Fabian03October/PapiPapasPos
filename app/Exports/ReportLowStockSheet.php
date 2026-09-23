<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportLowStockSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function collection()
    {
        return $this->service->lowStockIngredients()->map(fn ($i) => [
            $i->name, $i->stock_qty, $i->min_stock, $i->unit,
        ]);
    }

    public function headings(): array
    {
        return ['Insumo', 'Stock actual', 'Mínimo', 'Unidad'];
    }

    public function title(): string
    {
        return 'Stock bajo';
    }
}