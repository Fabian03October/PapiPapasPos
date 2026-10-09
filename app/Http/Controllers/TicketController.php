<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\ThermalTicketBuilder;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function venta(Sale $sale)
    {
        $sale->loadMissing(['items.product', 'items.variant', 'items.modifiers.modifier', 'customer', 'user']);

        return view('tickets.venta', compact('sale'));
    }

    public function comanda(Sale $sale)
    {
        $sale->loadMissing(['items.product', 'items.variant', 'items.modifiers.modifier.group']);

        return view('tickets.comanda', compact('sale'));
    }

    /**
     * Ticket + comanda ya acomodados a 32 columnas para la impresora
     * Bluetooth (public/js/impresora-bt.js). "recibido" es lo que pagó el
     * cliente en efectivo - no se guarda en la venta, así que solo se puede
     * imprimir el cambio justo al cobrar.
     */
    public function bluetooth(Request $request, Sale $sale, ThermalTicketBuilder $builder)
    {
        $received = $request->filled('recibido') ? (float) $request->query('recibido') : null;

        return response()->json([
            'ticket' => $builder->forSale($sale, $received),
            'comanda' => $builder->forKitchen($sale),
        ]);
    }

    public function pruebaImpresora()
    {
        return view('impresora.prueba');
    }
}
