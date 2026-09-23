<?php

namespace App\Filament\Pages;

use App\Exports\ReportExport;
use App\Services\ReportMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
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
            \App\Filament\Widgets\FinancialStatsOverview::class,
            \App\Filament\Widgets\SalesTrendChart::class,
            \App\Filament\Widgets\PeakHoursChart::class,
            \App\Filament\Widgets\TopProductsChart::class,
            \App\Filament\Widgets\InventoryStatsOverview::class,
            \App\Filament\Widgets\LowStockTable::class,
            \App\Filament\Widgets\DeadStockTable::class,
            \App\Filament\Widgets\EmployeeSalesTable::class,
            \App\Filament\Widgets\FrequentCustomersTable::class,
        ];
    }
}