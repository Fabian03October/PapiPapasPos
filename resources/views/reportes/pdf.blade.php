<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 0; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 6px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        th { background: #f3f3f3; }
        .muted { color: #666; font-size: 11px; }
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
        $employees = $service->salesByEmployee();
        $frequentCustomers = $service->frequentCustomers(10);
    @endphp

    <h2>Resumen</h2>
    <table>
        <tr><th>Ventas totales</th><td>${{ number_format($totals['net'], 2) }}</td></tr>
        <tr><th>Transacciones</th><td>{{ $totals['count'] }}</td></tr>
        <tr><th>Ticket promedio</th><td>${{ number_format($totals['average_ticket'], 2) }}</td></tr>
        <tr><th>Efectivo</th><td>${{ number_format($totals['cash'], 2) }}</td></tr>
        <tr><th>Tarjeta</th><td>${{ number_format($totals['card'], 2) }}</td></tr>
        <tr><th>Valor de inventario</th><td>${{ number_format($service->inventoryValue(), 2) }}</td></tr>
        <tr><th>Clientes nuevos</th><td>{{ $retention['new'] }}</td></tr>
        <tr><th>Clientes recurrentes</th><td>{{ $retention['recurring'] }}</td></tr>
    </table>

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