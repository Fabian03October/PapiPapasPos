<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gasto o ingreso que NO sale de la caja (renta, luz, nómina...). */
class GeneralMovement extends Model
{
    protected $fillable = ['type', 'amount', 'expense_category_id', 'description', 'occurred_on', 'user_id'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
