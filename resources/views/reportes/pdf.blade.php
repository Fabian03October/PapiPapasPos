<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #18181b; }
        h1 { font-size: 18px; margin-bottom: 0; color: #1d4ed8; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 6px; border-bottom: 2px solid #2563eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #e4e4e7; padding: 5px 8px; text-align: left; }
        th { background: #f4f4f5; }
        .muted { color: #71717a; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Reporte — Papi's Papas</h1>
    <p class="muted">Periodo: {{ $from->format('d/m/Y') }} — {{ $until->format('d/m/Y') }}</p>

    @php
        $totals = $service->totals();
        $retention = $service->customerRetention();
        $trend = $service->salesTrend();
        $topProducts = $service->topProducts(10);
        $lowStock = $service->lowStockIngredients();
        $ingredientMovements = $service->ingredientMovements();
        $employees = $service->salesByEmployee();
        $frequentCustomers = $service->frequentCustomers(10);
        $finanzas = $service->finances();
        $m = fn ($v) => '$'.number_format((float) $v, 2);
    @endphp

    <h2>Resumen</h2>
    <table>
        <tr><th>Ventas totales</th><td>${{ number_format($totals['net'], 2) }}</td></tr>
        <tr><th>Transacciones</th><td>{{ $totals['count'] }}</td></tr>
        <tr><th>Ticket promedio</th><td>${{ number_format($totals['average_ticket'], 2) }}</td></tr>
        <tr><th>Efectivo</th><td>${{ number_format($totals['cash'], 2) }}</td></tr>
        <tr><th>Tarjeta</th><td>${{ number_format($totals['card'], 2) }}</td></tr>
        <tr><th>Transferencia</th><td>${{ number_format($totals['transfer'], 2) }}</td></tr>
        <tr><th>Valor de inventario</th><td>${{ number_format($service->inventoryValue(), 2) }}</td></tr>
        <tr><th>Clientes nuevos</th><td>{{ $retention['new'] }}</td></tr>
        <tr><th>Clientes recurrentes</th><td>{{ $retention['recurring'] }}</td></tr>
    </table>

    <h2>Ingresos, gastos y ganancia</h2>
    <table>
        <tr><th></th><th>Caja (turnos)</th><th>Generales</th><th>Total</th></tr>
        <tr><th>Ingresos</th><td>{{ $m($finanzas['caja']['ingresos']) }}</td><td>{{ $m($finanzas['general']['ingresos']) }}</td><td>{{ $m($finanzas['ingresos']) }}</td></tr>
        <tr><th>Gastos</th><td>{{ $m($finanzas['caja']['gastos']) }}</td><td>{{ $m($finanzas['general']['gastos']) }}</td><td>{{ $m($finanzas['gastos']) }}</td></tr>
    </table>
    <table>
        <tr><th>Ventas</th><td>{{ $m($finanzas['ventas']) }}</td></tr>
        <tr><th>− Gastos</th><td>{{ $m($finanzas['gastos']) }}</td></tr>
        <tr><th>Ganancia</th><td><strong>{{ $m($finanzas['ganancia']) }}</strong></td></tr>
    </table>

    @if (count($finanzas['turnos']))
        <p class="muted">Por turno</p>
        <table>
            <tr><th>Apertura</th><th>Cajero</th><th>Ingresos</th><th>Gastos</th></tr>
            @foreach ($finanzas['turnos'] as $turno)
                <tr><td>{{ $turno['abrio']->format('d/m/Y H:i') }}</td><td>{{ $turno['cajero'] }}</td><td>{{ $m($turno['ingresos']) }}</td><td>{{ $m($turno['gastos']) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($finanzas['generales']->isNotEmpty())
        <p class="muted">Generales (fuera de caja)</p>
        <table>
            <tr><th>Fecha</th><th>Tipo</th><th>Categoría</th><th>Descripción</th><th>Monto</th></tr>
            @foreach ($finanzas['generales'] as $g)
                <tr><td>{{ $g->occurred_on->format('d/m/Y') }}</td><td>{{ $g->type === 'gasto' ? 'Gasto' : 'Ingreso' }}</td><td>{{ $g->category?->name ?? '—' }}</td><td>{{ $g->description }}</td><td>{{ $m($g->amount) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if (count($finanzas['por_categoria']))
        <p class="muted">Gastos por categoría</p>
        <table>
            @foreach ($finanzas['por_categoria'] as $categoria => $monto)
                <tr><th>{{ $categoria }}</th><td>{{ $m($monto) }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Ventas por día</h2>
    <table>
        <tr><th>Día</th><th>Ventas</th></tr>
        @foreach ($trend['labels'] as $i => $label)
            <tr><td>{{ $label }}</td><td>${{ number_format($trend['data'][$i], 2) }}</td></tr>
        @endforeach
    </table>

    <h2>Top productos</h2>
    <table>
        <tr><th>Producto</th><th>Ingresos</th></tr>
        @foreach ($topProducts['labels'] as $i => $label)
            <tr><td>{{ $label }}</td><td>${{ number_format($topProducts['data'][$i], 2) }}</td></tr>
        @endforeach
    </table>

    <h2>Alertas de stock bajo</h2>
    <table>
        <tr><th>Insumo</th><th>Stock actual</th><th>Mínimo</th><th>Unidad</th></tr>
        @forelse ($lowStock as $ingredient)
            <tr>
                <td>{{ $ingredient->name }}</td>
                <td>{{ $ingredient->stock_qty }}</td>
                <td>{{ $ingredient->min_stock }}</td>
                <td>{{ $ingredient->unit }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Sin alertas por el momento.</td></tr>
        @endforelse
    </table>

    <h2>Movimiento de insumos</h2>
    <table>
        <tr><th>Insumo</th><th>Entradas</th><th>Salidas (venta)</th><th>Merma</th><th>Unidad</th></tr>
        @forelse ($ingredientMovements as $row)
            <tr>
                <td>{{ $row->name }}</td>
                <td>{{ number_format($row->entradas, 2) }}</td>
                <td>{{ number_format($row->salidas, 2) }}</td>
                <td>{{ number_format($row->merma, 2) }}</td>
                <td>{{ $row->unit }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin insumos activos.</td></tr>
        @endforelse
    </table>

    <h2>Ventas por empleado</h2>
    <table>
        <tr><th>Cajero</th><th>Tickets</th><th>Total vendido</th></tr>
        @forelse ($employees as $row)
            <tr>
                <td>{{ $row->employee }}</td>
                <td>{{ $row->tickets }}</td>
                <td>${{ number_format($row->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Sin ventas en este periodo.</td></tr>
        @endforelse
    </table>

    <h2>Clientes frecuentes</h2>
    <table>
        <tr><th>Cliente</th><th>Visitas</th><th>Total gastado</th><th>Pedido favorito</th></tr>
        @forelse ($frequentCustomers as $row)
            <tr>
                <td>{{ $row->name }}</td>
                <td>{{ $row->visits }}</td>
                <td>${{ number_format($row->total, 2) }}</td>
                <td>{{ $row->favorite_product ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Sin clientes identificados en este periodo.</td></tr>
        @endforelse
    </table>
</body>
</html>