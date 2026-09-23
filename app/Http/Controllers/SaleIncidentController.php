<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Sale;
use App\Services\SaleIncidentService;
use Illuminate\Http\Request;

class SaleIncidentController extends Controller
{
    public function shiftHistory()
    {
        $session = CashSession::whereNull('closed_at')->latest('opened_at')->first();

        $sales = $session
            ? Sale::with(['items.product', 'items.modifiers.modifier', 'customer', 'incidents'])
                ->where('cash_session_id', $session->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('venta.historial', compact('sales', 'session'));
    }

        public function store(Request $request, SaleIncidentService $service)
    {
        $data = $request->validate([
            'sale_item_ids' => 'required|array|min:1',
            'sale_item_ids.*' => 'exists:sale_items,id',
            'type' => 'required|in:cancelacion,reposicion',
            'reason' => 'required|string|min:5|max:500',
            'mode' => 'required|in:codigo,pendiente',
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

        $requestedBy = auth()->user();

        if ($data['mode'] === 'pendiente') {
            $service->createPending($data['sale_item_ids'], $data['type'], $data['reason'], $requestedBy);

            return response()->json(['success' => true, 'message' => 'Solicitud enviada. Queda pendiente de que el manager la apruebe.']);
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