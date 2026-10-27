<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    protected $fillable = [
        'cash_session_id',
        'type',
        'amount',
        'reason',
        'expense_category_id',
        'user_id',
    ];

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
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
