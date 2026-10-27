<?php

namespace App\Exports;

use App\Services\ReportMetricsService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Ingresos, gastos (caja y generales), por categoría y ganancia del periodo. */
class ReportFinanceSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected ReportMetricsService $service) {}

    public function array(): array
    {
        $f = $this->service->finances();
        $n = fn ($v) => round((float) $v, 2);

        $rows = [
            ['Resumen', 'Ingresos de caja', '', '', $n($f['caja']['ingresos'])],
            ['Resumen', 'Gastos de caja', '', '', $n($f['caja']['gastos'])],
            ['Resumen', 'Ingresos generales', '', '', $n($f['general']['ingresos'])],
            ['Resumen', 'Gastos generales', '', '', $n($f['general']['gastos'])],
            ['Resumen', 'Ventas', '', '', $n($f['ventas'])],
            ['Resumen', 'Ganancia (ventas − gastos)', '', '', $n($f['ganancia'])],
        ];

        foreach ($f['turnos'] as $t) {
            $rows[] = ['Turno', $t['abrio']->format('d/m/Y H:i'), $t['cajero'], 'Ingresos', $n($t['ingresos'])];
            $rows[] = ['Turno', $t['abrio']->format('d/m/Y H:i'), $t['cajero'], 'Gastos', $n($t['gastos'])];
        }

        foreach ($f['generales'] as $g) {
            $rows[] = ['General', $g->occurred_on->format('d/m/Y'), $g->category?->name ?? '—',
                ($g->type === 'gasto' ? 'Gasto: ' : 'Ingreso: ').$g->description, $n($g->amount)];
        }

        foreach ($f['por_categoria'] as $categoria => $monto) {
            $rows[] = ['Gasto por categoría', $categoria, '', '', $n($monto)];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Sección', 'Concepto / fecha', 'Cajero / categoría', 'Detalle', 'Monto'];
    }

    public function title(): string
    {
        return 'Ingresos y gastos';
    }
}
