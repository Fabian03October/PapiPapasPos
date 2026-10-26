<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda #{{ $sale->folio }}</title>
    @include('tickets.partials.print-base')
    <style>
        .title { font-size: 22px; font-weight: bold; }
        .item { font-size: 19px; font-weight: bold; margin-top: 9px; }
        .mod { font-size: 14px; padding-left: 10px; font-weight: bold; }
        .mod-extra { font-size: 15px; padding-left: 10px; font-weight: bold; text-decoration: underline; }
        .customer { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body onload="window.print()">
    <p class="center title">COMANDA</p>
    <p class="center title">#{{ $sale->folio }}</p>
    @if ($sale->correctsSale)
        <p class="center mod-extra">CORRECCIÓN de #{{ $sale->correctsSale->folio }}</p>
    @endif
    @if ($sale->customer)
        <p class="center customer">{{ $sale->customer->name }}</p>
    @endif
    <p class="center">{{ $sale->created_at->format('H:i') }}</p>

    <div class="line"></div>

    @foreach ($sale->items as $item)
        @continue($item->cancelled_at)
        <p class="item">{{ $item->qty }}x {{ $item->product->name }}{{ $item->variant ? ' (' . $item->variant->name . ')' : '' }}</p>
        {{-- En el orden del negocio: presentación, Queso, Mayonesa, Kétchup... (ver SaleItem::orderedModifiers) --}}
        @foreach ($item->orderedModifiers() as $saleItemModifier)
            @if ($saleItemModifier->isExtra())
                <p class="mod-extra">+ EXTRA {{ $saleItemModifier->label() }}</p>
            @else
                <p class="mod">+ {{ $saleItemModifier->label() }}</p>
            @endif
        @endforeach
        @if ($item->notes)
            <p class="mod-extra">NOTA: {{ $item->notes }}</p>
        @endif
    @endforeach
</body>
</html>
