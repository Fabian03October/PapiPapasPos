<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Services\ReportMetricsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class EmployeeSalesTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Ventas por empleado';
        protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $service = ReportMetricsService::fromFilters($this->filters);

        return $table
            ->query(
                Sale::query()
                    ->join('users', 'users.id', '=', 'sales.user_id')
                    ->where('sales.status', 'pagada')
                    ->whereBetween('sales.created_at', [$service->from, $service->until])
                    ->selectRaw('users.id as id, users.name as employee, COUNT(*) as tickets, SUM(sales.total) as total')
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('total')
            )
            ->columns([
                TextColumn::make('employee')->label('Cajero'),
                TextColumn::make('tickets')->label('Tickets'),
                TextColumn::make('total')->label('Total vendido')->money('MXN'),
            ])
            ->paginated(false);
    }
}