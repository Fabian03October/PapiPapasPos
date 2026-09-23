<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionModifier extends Model
{
    protected $table = 'promotion_modifiers';

    protected $fillable = [
        'promotion_id',
        'modifier_id',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }
}