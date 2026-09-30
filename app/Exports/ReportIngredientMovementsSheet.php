<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportIngredientMovementsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function collection()
    {
        return $this->service->ingredientMovements()->map(fn ($i) => [
            $i->name, $i->entradas, $i->salidas, $i->merma, $i->unit,
        ]);
    }

    public function headings(): array
    {
        return ['Insumo', 'Entradas', 'Salidas (venta)', 'Merma', 'Unidad'];
    }

    public function title(): string
    {
        return 'Movimiento de insumos';
    }
}
