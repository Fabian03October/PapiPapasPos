<?php

namespace App\Filament\Pages;

use App\Exports\ReportExport;
use App\Filament\Widgets\DeadStockTable;
use App\Filament\Widgets\EmployeeSalesTable;
use App\Filament\Widgets\ExpensesByCategory;
use App\Filament\Widgets\FinanceStatsOverview;
use App\Filament\Widgets\FinancialStatsOverview;
use App\Filament\Widgets\FrequentCustomersTable;
use App\Filament\Widgets\IngredientMovementsTable;
use App\Filament\Widgets\InventoryStatsOverview;
use App\Filament\Widgets\LowStockTable;
use App\Filament\Widgets\PeakHoursChart;
use App\Filament\Widgets\SalesTrendChart;
use App\Filament\Widgets\TopProductsChart;
use App\Services\ReportMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('range')
                    ->label('Periodo')
                    ->options([
                        'today' => 'Hoy',
                        'yesterday' => 'Ayer',
                        'last_7_days' => 'Últimos 7 días',
                        'this_month' => 'Este mes',
                        'custom' => 'Personalizado',
                    ])
                    ->default('today')
                    ->live(),
                DatePicker::make('from')
                    ->label('Desde')
                    ->visible(fn (Get $get) => $get('range') === 'custom'),
                DatePicker::make('until')
                    ->label('Hasta')
                    ->visible(fn (Get $get) => $get('range') === 'custom'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')
                ->label('Descargar Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action(fn () => Excel::download(
                    new ReportExport(ReportMetricsService::fromFilters($this->filters)),
                    'reporte-papipapas.xlsx'
                )),

            Action::make('pdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(function () {
                    $service = ReportMetricsService::fromFilters($this->filters);

                    $pdf = Pdf::loadView('reportes.pdf', [
                        'service' => $service,
                        'from' => $service->from,
                        'until' => $service->until,
                    ]);

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        'reporte-papipapas.pdf'
                    );
                }),
        ];
    }

    public function getWidgets(): array
    {
        return [
            FinancialStatsOverview::class,
            FinanceStatsOverview::class,
            ExpensesByCategory::class,
            SalesTrendChart::class,
            PeakHoursChart::class,
            TopProductsChart::class,
            InventoryStatsOverview::class,
            IngredientMovementsTable::class,
            LowStockTable::class,
            DeadStockTable::class,
            EmployeeSalesTable::class,
            FrequentCustomersTable::class,
        ];
    }
}
