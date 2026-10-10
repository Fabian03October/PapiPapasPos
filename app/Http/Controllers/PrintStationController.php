<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PrintStationController extends Controller
{
    /**
     * Ventas del turno abierto que todavía no se han impreso — para que la
     * compu con la impresora las vaya recogiendo sola, sin importar desde
     * qué dispositivo se hizo la venta (celular, tablet, etc).
     */
    public function pending(Request $request)
    {
        $session = CashSession::open();

        if (! $session) {
            return response()->json(['sales' => []]);
        }

        $sales = Sale::where('cash_session_id', $session->id)
            ->where('status', 'pagada')
            ->whereNull('printed_at')
            // "desde": la estación Bluetooth solo toma ventas hechas después
            // de activarla, para no imprimir de golpe todo el turno.
            // El navegador la manda en UTC; created_at se guarda en hora local.
            ->when($request->filled('desde'), fn ($q) => $q->where(
                'created_at', '>=', Carbon::parse($request->query('desde'))->setTimezone(config('app.timezone'))
            ))
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

    /**
     * Devuelve a "pendiente" una venta que se reclamó pero no se pudo
     * imprimir (ej. la impresora Bluetooth se desconectó a la mitad), para
     * que se vuelva a intentar en vez de perder el ticket.
     */
    public function release(Sale $sale)
    {
        Sale::whereKey($sale->id)->update(['printed_at' => null]);

        return response()->json(['success' => true]);
    }
}
