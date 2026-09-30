<?php

namespace App\Filament\Widgets;

use App\Services\ReportMetricsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class IngredientMovementsTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Movimiento de insumos';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $service = ReportMetricsService::fromFilters($this->filters);

        return $table
            ->query($service->ingredientMovementsQuery())
            ->columns([
                TextColumn::make('name')->label('Insumo'),
                TextColumn::make('entradas')->label('Entradas')->numeric(decimalPlaces: 2),
                TextColumn::make('salidas')->label('Salidas (venta)')->numeric(decimalPlaces: 2),
                TextColumn::make('merma')->label('Merma')->numeric(decimalPlaces: 2)
                    ->color(fn ($state) => $state > 0 ? 'danger' : null)
                    ->weight(fn ($state) => $state > 0 ? 'bold' : null),
                TextColumn::make('unit')->label('Unidad'),
            ]);
    }
}
