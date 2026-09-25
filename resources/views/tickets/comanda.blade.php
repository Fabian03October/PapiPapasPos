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
    </style>
</head>
<body onload="window.print()">
    <p class="center title">COMANDA</p>
    <p class="center title">#{{ $sale->folio }}</p>
    <p class="center">{{ $sale->created_at->format('H:i') }}</p>

    <div class="line"></div>

    @foreach ($sale->items as $item)
        @continue($item->cancelled_at)
        <p class="item">{{ $item->qty }}x {{ $item->product->name }}{{ $item->variant ? ' (' . $item->variant->name . ')' : '' }}</p>
        @foreach ($item->modifiers as $saleItemModifier)
            @if ($saleItemModifier->modifier)
                @if ($saleItemModifier->modifier->group?->name === 'Extras')
                    <p class="mod-extra">+ EXTRA {{ $saleItemModifier->modifier->name }}</p>
                @else
                    <p class="mod">+ {{ $saleItemModifier->modifier->name }}</p>
                @endif
            @endif
        @endforeach
    @endforeach
</body>
</html>
