<?php

namespace App\Http\Controllers;

use App\Models\Sale;

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
}
