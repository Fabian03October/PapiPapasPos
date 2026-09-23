<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'type',
        'percent_value',
        'combo_price',
        'valid_from',
        'valid_until',
        'days_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_products')
            ->withPivot('qty_required');
    }

    public function modifiers()
    {
        return $this->belongsToMany(Modifier::class, 'promotion_modifiers');
    }
}