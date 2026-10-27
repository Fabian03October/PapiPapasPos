<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Gastos del periodo (caja + generales) por categoría. */
class ExpensesByCategory extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.expenses-by-category';

    protected function getViewData(): array
    {
        $porCategoria = ReportMetricsService::fromFilters($this->filters ?? [])->finances()['por_categoria'];

        return ['rows' => $porCategoria, 'total' => array_sum($porCategoria)];
    }
}
