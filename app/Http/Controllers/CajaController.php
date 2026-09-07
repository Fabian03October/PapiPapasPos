<?php

namespace App\Http\Controllers;

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
}