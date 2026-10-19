<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashSession extends Model
{
    protected $fillable = [
        'user_id',
        'closed_by',
        'opening_amount',
        'opened_at',
        'closed_at',
        'expected_amount',
        'counted_amount',
        'difference',
        'fund_counted_amount',
        'fund_difference',
        'sales_expected_amount',
        'sales_counted_amount',
        'sales_difference',
        'difference_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function movements()
    {
        return $this->hasMany(CashMovement::class);
    }

    public function inventoryCount()
    {
        return $this->hasOne(InventoryCount::class);
    }

    public static function open(): ?self
    {
        return static::whereNull('closed_at')->latest('opened_at')->first();
    }

    /**
     * El dinero de las ventas que debe haber en la caja (sin el fondo):
     * ventas en efectivo + ingresos − gastos. Las ventas canceladas o
     * deshechas ya tienen total 0.
     */
    public function salesCashExpected(): float
    {
        $cashSales = $this->sales()->where('payment_method', 'efectivo')->sum('total');
        $ingresos = $this->movements()->where('type', 'ingreso')->sum('amount');
        $gastos = $this->movements()->where('type', 'gasto')->sum('amount');

        return round((float) $cashSales + (float) $ingresos - (float) $gastos, 2);
    }

    /** Todo el efectivo que debe haber: fondo + dinero de las ventas. */
    public function calculateExpectedCash(): float
    {
        return round((float) $this->opening_amount + $this->salesCashExpected(), 2);
    }
}