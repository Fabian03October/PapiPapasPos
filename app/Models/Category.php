<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active', 'gives_stamp'];

    protected $casts = ['gives_stamp' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}