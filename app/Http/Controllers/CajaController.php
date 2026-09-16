<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
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

    public function showClose()
    {
        $session = CashSession::open();

        if (! $session) {
            return redirect()->route('caja.abrir');
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