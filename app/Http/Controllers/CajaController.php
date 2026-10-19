<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CashSessionDenomination;
use App\Models\Ingredient;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\InventoryMovement;
use App\Services\CorteCajaService;
use App\Services\ThermalTicketBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function abrir()
    {
        if (CashSession::open()) {
            return redirect()->route('venta.index');
        }

        $ultimaCerrada = CashSession::whereNotNull('closed_at')
            ->latest('closed_at')
            ->first();

        // Se propone el fondo que se dejó en la caja al cerrar el turno
        // anterior (o su fondo inicial, si se cerró antes de que existiera el
        // conteo separado). Sin turnos anteriores, no hay qué proponer.
        $fondoSugerido = $ultimaCerrada
            ? (float) ($ultimaCerrada->fund_counted_amount ?? $ultimaCerrada->opening_amount)
            : null;

        return view('caja.abrir', [
            'ultimaCerrada' => $ultimaCerrada,
            'fondoSugerido' => $fondoSugerido,
        ]);
    }

    public function abrirStore(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        if (CashSession::open()) {
            return response()->json(['success' => false, 'message' => 'Ya hay una caja abierta'], 422);
        }

        CashSession::create([
            'user_id' => auth()->id(),
            'opening_amount' => $request->opening_amount,
            'opened_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'redirect' => route('venta.index'),
        ]);
    }

    public function resumen()
    {
        $session = CashSession::open();

        if (! $session) {
            return redirect()->route('caja.abrir');
        }

        $session->load(['movements' => fn ($q) => $q->latest(), 'movements.user']);

        $cashSales = $session->sales()->where('payment_method', 'efectivo')->sum('total');
        $cardSales = $session->sales()->where('payment_method', 'tarjeta')->sum('total');
        $transferSales = $session->sales()->where('payment_method', 'transferencia')->sum('total');
        $ingresos = $session->movements()->where('type', 'ingreso')->sum('amount');
        $gastos = $session->movements()->where('type', 'gasto')->sum('amount');

        return view('caja.resumen', [
            'session' => $session,
            'cashSales' => $cashSales,
            'cardSales' => $cardSales,
            'transferSales' => $transferSales,
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'expected' => $session->calculateExpectedCash(),
        ]);
    }

    /** Corregir el fondo inicial durante el turno (si se capturó mal al abrir). */
    public function updateFund(Request $request)
    {
        $request->validate(['opening_amount' => 'required|numeric|min:0|max:999999']);

        $session = CashSession::open();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        $session->update(['opening_amount' => $request->opening_amount]);

        return response()->json(['success' => true]);
    }

    public function addMovement(Request $request)
    {
        $request->validate([
            'type' => 'required|in:ingreso,gasto',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $session = CashSession::open();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        CashMovement::create([
            'cash_session_id' => $session->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'reason' => $request->reason,
            'user_id' => auth()->id(),
        ]);

        return response()->json(['success' => true]);
    }

    public function showInventoryCount()
    {
        $session = CashSession::open();

        if (! $session) {
            return redirect()->route('caja.abrir');
        }

        if ($session->inventoryCount()->exists()) {
            return redirect()->route('caja.cerrar');
        }

        $ingredients = Ingredient::where('is_active', true)->orderBy('name')->get();

        // Sin insumos dados de alta no hay nada que contar: antes la pantalla
        // quedaba vacía con el botón bloqueado y nunca se llegaba a contar el
        // dinero. Se registra el conteo (vacío) y se pasa directo al corte.
        if ($ingredients->isEmpty()) {
            InventoryCount::create(['cash_session_id' => $session->id, 'user_id' => auth()->id()]);

            return redirect()->route('caja.cerrar');
        }

        return view('caja.inventario', [
            'session' => $session,
            'ingredients' => $ingredients,
        ]);
    }

    public function storeInventoryCount(Request $request)
    {
        $session = CashSession::open();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        if ($session->inventoryCount()->exists()) {
            return response()->json(['success' => false, 'message' => 'El conteo de inventario ya se registró para este turno'], 422);
        }

        $activeIngredientIds = Ingredient::where('is_active', true)->pluck('id');

        $request->validate([
            'counts' => 'present|array',
        ]);

        foreach ($activeIngredientIds as $ingredientId) {
            if (! array_key_exists($ingredientId, $request->counts)) {
                return response()->json(['success' => false, 'message' => 'Falta contar uno o más insumos'], 422);
            }
        }

        $inventoryCount = InventoryCount::create([
            'cash_session_id' => $session->id,
            'user_id' => auth()->id(),
        ]);

        foreach ($activeIngredientIds as $ingredientId) {
            $ingredient = Ingredient::find($ingredientId);
            $expectedQty = (float) $ingredient->stock_qty;
            $countedQty = (float) $request->counts[$ingredientId];
            $difference = round($countedQty - $expectedQty, 2);

            InventoryCountItem::create([
                'inventory_count_id' => $inventoryCount->id,
                'ingredient_id' => $ingredient->id,
                'expected_qty' => $expectedQty,
                'counted_qty' => $countedQty,
                'difference' => $difference,
            ]);

            if ($difference !== 0.0) {
                InventoryMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => $difference < 0 ? 'merma' : 'ajuste',
                    'qty' => $difference,
                    'unit_cost' => $ingredient->cost_per_unit,
                    'reference_type' => 'inventory_count',
                    'reference_id' => $inventoryCount->id,
                    'user_id' => auth()->id(),
                ]);

                $ingredient->update(['stock_qty' => $countedQty]);
            }
        }

        return response()->json([
            'success' => true,
            'redirect' => route('caja.cerrar'),
        ]);
    }

    public function showClose()
    {
        $session = CashSession::open();

        if (! $session) {
            return redirect()->route('caja.abrir');
        }

        if (! $session->inventoryCount()->exists()) {
            return redirect()->route('caja.cerrar.inventario');
        }

        return view('caja.cerrar', [
            'session' => $session,
            'fundExpected' => (float) $session->opening_amount,
            'salesExpected' => $session->salesCashExpected(),
            'cashSales' => (float) $session->sales()->where('payment_method', 'efectivo')->sum('total'),
            'ingresos' => (float) $session->movements()->where('type', 'ingreso')->sum('amount'),
            'gastos' => (float) $session->movements()->where('type', 'gasto')->sum('amount'),
        ]);
    }

    /**
     * Cierre con dos conteos: el fondo y el dinero de las ventas. Cada uno
     * llega como "cuadra" (matches) o con su desglose de billetes y monedas.
     */
    public function closeStore(Request $request)
    {
        $request->validate([
            'fund' => 'required|array',
            'fund.matches' => 'required|boolean',
            'fund.denominations' => 'array',
            'fund.denominations.*.value' => 'required|numeric|min:0',
            'fund.denominations.*.qty' => 'required|integer|min:0',
            'sales' => 'required|array',
            'sales.matches' => 'required|boolean',
            'sales.denominations' => 'array',
            'sales.denominations.*.value' => 'required|numeric|min:0',
            'sales.denominations.*.qty' => 'required|integer|min:0',
            'difference_reason' => 'nullable|string|max:255',
        ]);

        $session = CashSession::open();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        if (! $session->inventoryCount()->exists()) {
            return response()->json(['success' => false, 'message' => 'Falta registrar el conteo físico de inventario'], 422);
        }

        $fundExpected = round((float) $session->opening_amount, 2);
        $salesExpected = $session->salesCashExpected();

        $contar = function (array $part, float $expected): float {
            if ($part['matches']) {
                return $expected;
            }

            return round(collect($part['denominations'] ?? [])->sum(fn ($row) => $row['value'] * $row['qty']), 2);
        };

        $fundCounted = $contar($request->fund, $fundExpected);
        $salesCounted = $contar($request->sales, $salesExpected);
        $fundDifference = round($fundCounted - $fundExpected, 2);
        $salesDifference = round($salesCounted - $salesExpected, 2);

        if ((abs($fundDifference) >= 0.01 || abs($salesDifference) >= 0.01) && blank($request->difference_reason)) {
            return response()->json(['success' => false, 'message' => 'Escribe por qué no cuadra.'], 422);
        }

        $closed = DB::transaction(function () use ($request, $session, $fundExpected, $salesExpected, $fundCounted, $salesCounted, $fundDifference, $salesDifference) {
            // Solo se cierra si sigue abierta (un doble toque no cierra dos veces).
            $updated = CashSession::whereKey($session->id)->whereNull('closed_at')->update([
                'expected_amount' => round($fundExpected + $salesExpected, 2),
                'counted_amount' => round($fundCounted + $salesCounted, 2),
                'difference' => round($fundDifference + $salesDifference, 2),
                'fund_counted_amount' => $fundCounted,
                'fund_difference' => $fundDifference,
                'sales_expected_amount' => $salesExpected,
                'sales_counted_amount' => $salesCounted,
                'sales_difference' => $salesDifference,
                'difference_reason' => $request->difference_reason,
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            if (! $updated) {
                return false;
            }

            foreach (['fondo' => $request->fund, 'ventas' => $request->sales] as $kind => $part) {
                foreach ($part['denominations'] ?? [] as $row) {
                    if ($row['qty'] > 0) {
                        CashSessionDenomination::create([
                            'cash_session_id' => $session->id,
                            'kind' => $kind,
                            'denomination_value' => $row['value'],
                            'quantity' => $row['qty'],
                        ]);
                    }
                }
            }

            return true;
        });

        if (! $closed) {
            return response()->json(['success' => false, 'message' => 'Esta caja ya se había cerrado.'], 422);
        }

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'redirect' => route('caja.abrir'),
        ]);
    }

    public function reporteCierre(CashSession $session, CorteCajaService $corte)
    {
        return view('tickets.cierre-caja', $corte->resumen($session));
    }

    /** El corte para la impresora Bluetooth (impresora-bt.js), a 32 columnas. */
    public function reporteCierreBluetooth(CashSession $session, ThermalTicketBuilder $builder)
    {
        return response()->json(['ticket' => $builder->forCashClose($session)]);
    }
}