<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de caja</title>
    @include('tickets.partials.print-base')
    <style>
        body { font-size: 12px; }
        .title { font-size: 16px; font-weight: bold; }
        .section { font-size: 12px; font-weight: bold; margin-top: 8px; text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        td { padding: 1px 0; vertical-align: top; }
        .small { font-size: 10px; }
        .signature-line { border-top: 1px solid #000; margin-top: 30px; padding-top: 3px; }
    </style>
</head>
<body onload="window.print()">
    <p class="center title">Papi's Papas</p>
    <p class="center">CORTE DE CAJA</p>
    <div class="line"></div>

    <p>Apertura: {{ $session->opened_at->format('d/m/Y H:i') }}</p>
    <p>Cierre: {{ $session->closed_at->format('d/m/Y H:i') }}</p>
    <p>Abrió: {{ $session->user?->name ?? '-' }}</p>
    <p>Cerró: {{ $session->closedByUser?->name ?? '-' }}</p>

    <div class="line"></div>

    {{-- 1. Fondo de caja --}}
    <p class="section">1. Fondo de caja</p>
    <table>
        <tr><td>Fondo inicial</td><td class="right">${{ number_format($fondo['esperado'], 2) }}</td></tr>
        @if ($separado)
            <tr><td>Contado</td><td class="right">${{ number_format($fondo['contado'], 2) }}</td></tr>
            @if (abs($fondo['diferencia']) >= 0.01)
                <tr><td><strong>{{ $fondo['diferencia'] > 0 ? 'Sobra' : 'Falta' }}</strong></td><td class="right"><strong>${{ number_format(abs($fondo['diferencia']), 2) }}</strong></td></tr>
            @endif
        @endif
    </table>

    <div class="line"></div>

    {{-- 2. Ventas del turno --}}
    <p class="section">2. Ventas del turno ({{ $salesCount }})</p>
    <table>
        @foreach ($porMetodo as $metodo)
            <tr><td>{{ $metodo['label'] }} ({{ $metodo['count'] }})</td><td class="right">${{ number_format($metodo['total'], 2) }}</td></tr>
        @endforeach
        <tr><td><strong>Total ventas</strong></td><td class="right"><strong>${{ number_format($ventasTotal, 2) }}</strong></td></tr>
        @if ($deshechas > 0)
            <tr><td class="small">Deshechas o corregidas</td><td class="right small">{{ $deshechas }}</td></tr>
        @endif
    </table>

    <div class="line"></div>

    {{-- 3. Dinero de las ventas en efectivo --}}
    <p class="section">3. Dinero de ventas (efectivo)</p>
    <table>
        <tr><td>Ventas en efectivo</td><td class="right">${{ number_format($porMetodo['efectivo']['total'], 2) }}</td></tr>
        @if ($ingresos > 0)<tr><td>+ Ingresos</td><td class="right">${{ number_format($ingresos, 2) }}</td></tr>@endif
        @if ($gastos > 0)<tr><td>− Gastos</td><td class="right">${{ number_format($gastos, 2) }}</td></tr>@endif
        <tr><td>Esperado</td><td class="right">${{ number_format($ventasEfectivo['esperado'], 2) }}</td></tr>
        @if ($separado)
            <tr><td>Contado</td><td class="right">${{ number_format($ventasEfectivo['contado'], 2) }}</td></tr>
            @if (abs($ventasEfectivo['diferencia']) >= 0.01)
                <tr><td><strong>{{ $ventasEfectivo['diferencia'] > 0 ? 'Sobra' : 'Falta' }}</strong></td><td class="right"><strong>${{ number_format(abs($ventasEfectivo['diferencia']), 2) }}</strong></td></tr>
            @endif
        @endif
    </table>

    <div class="line"></div>

    {{-- 4. Total en caja --}}
    <p class="section">4. Total en caja</p>
    <table>
        <tr><td>Fondo + ventas en efectivo</td><td class="right">${{ number_format($totalCaja['esperado'], 2) }}</td></tr>
        @if ($totalCaja['contado'] !== null)
            <tr><td><strong>Contado</strong></td><td class="right"><strong>${{ number_format($totalCaja['contado'], 2) }}</strong></td></tr>
            <tr><td><strong>Diferencia</strong></td><td class="right"><strong>${{ number_format($totalCaja['diferencia'], 2) }}</strong></td></tr>
        @endif
    </table>
    @if ($motivo)
        <p class="small">Motivo: {{ $motivo }}</p>
    @endif

    <div class="line"></div>

    <p class="section">Mermas del turno</p>
    @forelse ($mermas as $merma)
        <p class="small">{{ $merma->name }}: {{ number_format($merma->qty, 2) }} {{ $merma->unit }} — ${{ number_format($merma->value, 2) }}</p>
    @empty
        <p class="small">Sin mermas registradas.</p>
    @endforelse
    @if ($mermas->isNotEmpty())
        <p><strong>Total mermas: ${{ number_format($mermasTotal, 2) }}</strong></p>
    @endif

    <div class="line"></div>

    <p class="section">Stock mínimo</p>
    @forelse ($lowStock as $ingredient)
        <p class="small">{{ $ingredient->name }}: {{ number_format($ingredient->stock_qty, 2) }} / mín {{ number_format($ingredient->min_stock, 2) }} {{ $ingredient->unit }}</p>
    @empty
        <p class="small">Sin alertas de stock bajo.</p>
    @endforelse

    <div class="line"></div>

    <div class="signature-line">
        <p class="center">{{ $session->closedByUser?->name ?? '-' }}</p>
        <p class="center small">Firma de quien cerró la caja</p>
    </div>
</body>
</html>
