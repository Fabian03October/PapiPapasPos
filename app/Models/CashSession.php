<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashSession extends Model
{
    protected $fillable = [
        'user_id',
        'opening_amount',
        'opened_at',
        'closed_at',
        'expected_amount',
        'counted_amount',
        'difference',
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

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function movements()
    {
        return $this->hasMany(CashMovement::class);
    }

    public static function open(): ?self
    {
        return static::whereNull('closed_at')->latest('opened_at')->first();
    }

    public function calculateExpectedCash(): float
    {
        $cashSales = $this->sales()->where('payment_method', 'efectivo')->sum('total');
        $ingresos = $this->movements()->where('type', 'ingreso')->sum('amount');
        $gastos = $this->movements()->where('type', 'gasto')->sum('amount');

        return (float) $this->opening_amount + (float) $cashSales + (float) $ingresos - (float) $gastos;
    }
}