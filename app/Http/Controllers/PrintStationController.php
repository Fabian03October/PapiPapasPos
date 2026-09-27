<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Sale;

class PrintStationController extends Controller
{
    /**
     * Ventas del turno abierto que todavía no se han impreso — para que la
     * compu con la impresora las vaya recogiendo sola, sin importar desde
     * qué dispositivo se hizo la venta (celular, tablet, etc).
     */
    public function pending()
    {
        $session = CashSession::open();

        if (! $session) {
            return response()->json(['sales' => []]);
        }

        $sales = Sale::where('cash_session_id', $session->id)
            ->where('status', 'pagada')
            ->whereNull('printed_at')
            ->orderBy('created_at')
            ->get(['id', 'folio']);

        return response()->json(['sales' => $sales]);
    }

    /**
     * Reclama la venta de forma atómica antes de imprimirla: el UPDATE con
     * WHERE printed_at IS NULL solo puede "ganarlo" una petición cuando dos
     * dispositivos/pestañas revisan pendientes casi al mismo tiempo — evita
     * que ambos la impriman.
     */
    public function markPrinted(Sale $sale)
    {
        $claimed = Sale::whereKey($sale->id)
            ->whereNull('printed_at')
            ->update(['printed_at' => now()]);

        return response()->json(['success' => true, 'claimed' => (bool) $claimed]);
    }
}
