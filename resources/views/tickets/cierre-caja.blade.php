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

    <p class="section">Ventas</p>
    <table>
        <tr><td>Total de ventas</td><td class="right">{{ $salesCount }}</td></tr>
        <tr><td>Efectivo ({{ $cashCount }})</td><td class="right">${{ number_format($cashTotal, 2) }}</td></tr>
        <tr><td>Tarjeta ({{ $cardCount }})</td><td class="right">${{ number_format($cardTotal, 2) }}</td></tr>
        <tr><td><strong>TOTAL</strong></td><td class="right"><strong>${{ number_format($grandTotal, 2) }}</strong></td></tr>
    </table>

    <div class="line"></div>

    <p class="section">Movimientos</p>
    <table>
        <tr><td>Ingresos</td><td class="right">${{ number_format($ingresos, 2) }}</td></tr>
        <tr><td>Gastos</td><td class="right">${{ number_format($gastos, 2) }}</td></tr>
    </table>

    <div class="line"></div>

    <p class="section">Efectivo</p>
    <table>
        <tr><td>Fondo inicial</td><td class="right">${{ number_format($session->opening_amount, 2) }}</td></tr>
        <tr><td>Esperado</td><td class="right">${{ number_format($session->expected_amount, 2) }}</td></tr>
        <tr><td>Contado</td><td class="right">${{ number_format($session->counted_amount, 2) }}</td></tr>
        <tr><td><strong>Diferencia</strong></td><td class="right"><strong>${{ number_format($session->difference, 2) }}</strong></td></tr>
    </table>

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
