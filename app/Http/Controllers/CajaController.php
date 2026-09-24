<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Ingredient;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;

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

        return view('caja.abrir', [
            'ultimaCerrada' => $ultimaCerrada,
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
        $ingresos = $session->movements()->where('type', 'ingreso')->sum('amount');
        $gastos = $session->movements()->where('type', 'gasto')->sum('amount');

        return view('caja.resumen', [
            'session' => $session,
            'cashSales' => $cashSales,
            'cardSales' => $cardSales,
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'expected' => $session->calculateExpectedCash(),
        ]);
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
            'counts' => 'required|array',
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
            'expected' => $session->calculateExpectedCash(),
        ]);
    }

    public function closeStore(Request $request)
    {
        $request->validate([
            'denominations' => 'required|array',
            'denominations.*.value' => 'required|numeric',
            'denominations.*.qty' => 'required|integer|min:0',
        ]);

        $session = CashSession::open();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        if (! $session->inventoryCount()->exists()) {
            return response()->json(['success' => false, 'message' => 'Falta registrar el conteo físico de inventario'], 422);
        }

        $countedAmount = 0;

        foreach ($request->denominations as $row) {
            if ($row['qty'] > 0) {
                \App\Models\CashSessionDenomination::create([
                    'cash_session_id' => $session->id,
                    'denomination_value' => $row['value'],
                    'quantity' => $row['qty'],
                ]);
            }
            $countedAmount += $row['value'] * $row['qty'];
        }

        $expected = $session->calculateExpectedCash();
        $difference = $countedAmount - $expected;

        $session->update([
            'expected_amount' => $expected,
            'counted_amount' => $countedAmount,
            'difference' => $difference,
            'closed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'redirect' => route('caja.abrir'),
        ]);
    }
}