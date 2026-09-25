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

    public function markPrinted(Sale $sale)
    {
        $sale->printed_at = now();
        $sale->save();

        return response()->json(['success' => true]);
    }
}
