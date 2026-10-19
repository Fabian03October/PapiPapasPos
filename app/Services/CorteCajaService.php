<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Sale;

/**
 * Los números del corte de caja, en un solo lugar: los usan el ticket de
 * Chrome (tickets/cierre-caja) y el de la impresora Bluetooth
 * (ThermalTicketBuilder::forCashClose), para que digan exactamente lo mismo.
 *
 * Separado como lo pidió el cliente:
 *   1. Fondo de caja (con el que se abrió)
 *   2. Ventas del turno, por método de pago
 *   3. Dinero de las ventas en efectivo (ventas + ingresos − gastos)
 *   4. Total en caja = fondo + dinero de las ventas
 */
class CorteCajaService
{
    public function resumen(CashSession $session): array
    {
        $session->loadMissing(['user', 'closedByUser']);

        $sales = $session->sales()->where('status', '!=', 'cancelada')->get();
        $porMetodo = [];
        foreach (Sale::PAYMENT_METHODS as $method => $label) {
            $delMetodo = $sales->where('payment_method', $method);
            $porMetodo[$method] = ['label' => $label, 'count' => $delMetodo->count(), 'total' => (float) $delMetodo->sum('total')];
        }

        $ingresos = (float) $session->movements()->where('type', 'ingreso')->sum('amount');
        $gastos = (float) $session->movements()->where('type', 'gasto')->sum('amount');

        $fondo = (float) $session->opening_amount;
        $ventasEsperado = $session->sales_expected_amount !== null
            ? (float) $session->sales_expected_amount
            : $session->salesCashExpected();

        // Turnos cerrados antes de que existieran los dos conteos: solo hay el total.
        $separado = $session->sales_counted_amount !== null;

        $mermas = InventoryMovement::where('type', 'merma')
            ->whereBetween('created_at', [$session->opened_at, $session->closed_at ?? now()])
            ->with('ingredient')
            ->get()
            ->filter(fn ($movement) => $movement->ingredient !== null)
            ->groupBy('ingredient_id')
            ->map(function ($movements) {
                $ingredient = $movements->first()->ingredient;

                return (object) [
                    'name' => $ingredient->name,
                    'unit' => $ingredient->unit,
                    'qty' => $movements->sum(fn ($m) => abs($m->qty)),
                    'value' => $movements->sum(fn ($m) => abs($m->qty) * (float) $m->unit_cost),
                ];
            })
            ->sortByDesc('value')
            ->values();

        return [
            'session' => $session,
            'salesCount' => $sales->count(),
            'porMetodo' => $porMetodo,
            'ventasTotal' => (float) $sales->sum('total'),
            'deshechas' => $session->sales()->whereNotNull('revert_type')->count(),
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'separado' => $separado,
            'fondo' => [
                'esperado' => $fondo,
                'contado' => $separado ? (float) $session->fund_counted_amount : null,
                'diferencia' => $separado ? (float) $session->fund_difference : null,
            ],
            'ventasEfectivo' => [
                'esperado' => $ventasEsperado,
                'contado' => $separado ? (float) $session->sales_counted_amount : null,
                'diferencia' => $separado ? (float) $session->sales_difference : null,
            ],
            'totalCaja' => [
                'esperado' => round($fondo + $ventasEsperado, 2),
                'contado' => $session->counted_amount !== null ? (float) $session->counted_amount : null,
                'diferencia' => $session->difference !== null ? (float) $session->difference : null,
            ],
            'motivo' => $session->difference_reason,
            'mermas' => $mermas,
            'mermasTotal' => $mermas->sum('value'),
            'lowStock' => Ingredient::where('is_active', true)->whereColumn('stock_qty', '<=', 'min_stock')->orderBy('stock_qty')->get(),
        ];
    }
}
