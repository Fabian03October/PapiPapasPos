<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #{{ $sale->folio }}</title>
    @include('tickets.partials.print-base')
    <style>
        body { font-size: 13px; }
        .bold { font-weight: bold; }
        .name { font-size: 17px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td { padding: 2px 0; vertical-align: top; }
        .mod { padding-left: 8px; font-size: 11px; color: #000; }
    </style>
</head>
<body onload="window.print()">
    <p class="center name">Papi's Papas</p>
    <p class="center">Ticket de venta</p>
    <div class="line"></div>

    <p>Folio: #{{ $sale->folio }}</p>
    <p>Fecha: {{ $sale->created_at->format('d/m/Y H:i') }}</p>
    <p>Cajero: {{ $sale->user->name ?? '-' }}</p>
    @if ($sale->customer)
        <p>Cliente: {{ $sale->customer->name }}</p>
    @endif

    <div class="line"></div>

    <table>
        @foreach ($sale->items as $item)
            @continue($item->cancelled_at)
            <tr>
                <td>{{ $item->qty }}x {{ $item->product->name }}{{ $item->variant ? ' (' . $item->variant->name . ')' : '' }}</td>
                <td class="right">${{ number_format($item->line_total, 2) }}</td>
            </tr>
            @foreach ($item->modifiers as $saleItemModifier)
                @if ($saleItemModifier->modifier)
                    <tr>
                        <td class="mod">+ {{ $saleItemModifier->modifier->name }}</td>
                        <td class="right mod">
                            @if ($saleItemModifier->price_delta > 0)
                                ${{ number_format($saleItemModifier->price_delta * $item->qty, 2) }}
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    </table>

    <div class="line"></div>

    <table>
        <tr><td>Subtotal</td><td class="right">${{ number_format($sale->subtotal, 2) }}</td></tr>
        @if ($sale->discount > 0)
            <tr><td>Descuento</td><td class="right">-${{ number_format($sale->discount, 2) }}</td></tr>
        @endif
        <tr class="bold"><td>TOTAL</td><td class="right">${{ number_format($sale->total, 2) }}</td></tr>
    </table>

    <div class="line"></div>

    <p>Pago: {{ $sale->payment_method === 'efectivo' ? 'Efectivo' : 'Tarjeta' }}</p>

    <p class="center" style="margin-top: 8px;">¡Gracias por tu compra!</p>
</body>
</html>
