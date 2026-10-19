<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Sale;
use App\Services\SaleIncidentService;
use App\Services\SaleRevertService;
use Illuminate\Http\Request;

class SaleIncidentController extends Controller
{
    public function shiftHistory()
    {
        $session = CashSession::whereNull('closed_at')->latest('opened_at')->first();

        $sales = $session
            ? Sale::with(['items.product', 'items.modifiers.modifier.group', 'customer', 'incidents', 'replacedBy', 'correctsSale', 'revertedByUser'])
                ->where('cash_session_id', $session->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('venta.historial', compact('sales', 'session'));
    }

    /**
     * Deshacer una venta: se deshace y su orden regresa para corregirla
     * (ver SaleRevertService para las reglas).
     */
    public function undo(Request $request, Sale $sale, SaleRevertService $reverts)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:255']);

        if ($why = $reverts->whyNot($sale, $request->user())) {
            return response()->json(['success' => false, 'message' => $why], 422);
        }

        return response()->json(['success' => true, 'order' => $reverts->revert($sale, $request->user(), $data['reason'])]);
    }

        public function store(Request $request, SaleIncidentService $service)
    {
        $data = $request->validate([
            'sale_item_ids' => 'required|array|min:1',
            'sale_item_ids.*' => 'exists:sale_items,id',
            'type' => 'required|in:cancelacion,reposicion',
            'reason' => 'required|string|min:5|max:500',
            'mode' => 'required|in:codigo,pendiente,directo',
            'code' => 'nullable|string',
        ]);

        if ($data['type'] === 'cancelacion' && $data['mode'] === 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Una cancelación necesita el código temporal en el momento.',
            ], 422);
        }

        $items = \App\Models\SaleItem::whereIn('id', $data['sale_item_ids'])->get();

        if ($items->pluck('sale_id')->unique()->count() > 1) {
            return response()->json(['success' => false, 'message' => 'Los productos seleccionados no son de la misma venta.'], 422);
        }

        if ($items->contains(fn ($i) => $i->cancelled_at !== null)) {
            return response()->json(['success' => false, 'message' => 'Ese producto ya estaba cancelado.'], 422);
        }

        // Solo productos del turno abierto: cancelar algo de un turno ya
        // cerrado cambiaría el total de un corte que ya se contó y entregó.
        $openSession = CashSession::open();
        $saleSessionId = Sale::whereKey($items->first()->sale_id)->value('cash_session_id');

        if (! $openSession || $saleSessionId !== $openSession->id) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden cancelar o reponer productos del turno abierto.'], 422);
        }

        $requestedBy = auth()->user();

        // Manager y gerente cancelan/reponen directo, sin código: lo autorizan
        // ellos mismos (queda registrado). Solo el cajero necesita código.
        if ($data['mode'] === 'directo') {
            if (! $requestedBy->puedeAutorizar()) {
                return response()->json(['success' => false, 'message' => 'Se necesita el código temporal del manager o gerente.'], 403);
            }

            $service->createAndApprove($data['sale_item_ids'], $data['type'], $data['reason'], $requestedBy, $requestedBy, 'pin');

            return response()->json([
                'success' => true,
                'message' => $data['type'] === 'cancelacion' ? 'Producto cancelado.' : 'Reposición registrada.',
            ]);
        }

        if ($data['mode'] === 'pendiente') {
            $service->createPending($data['sale_item_ids'], $data['type'], $data['reason'], $requestedBy);

            return response()->json(['success' => true, 'message' => 'Solicitud enviada. Queda pendiente de que el manager o gerente la apruebe.']);
        }

        $manager = $service->redeemTemporaryCode($data['code'] ?? '');

        if (! $manager) {
            return response()->json(['success' => false, 'message' => 'Código inválido o expirado.'], 422);
        }

        $service->createAndApprove($data['sale_item_ids'], $data['type'], $data['reason'], $requestedBy, $manager);

        return response()->json([
            'success' => true,
            'message' => $data['type'] === 'cancelacion' ? 'Producto cancelado.' : 'Reposición registrada.',
        ]);
    }
}