<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Categoría de gastos/ingresos (Renta, Servicios, Insumos...). */
class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return array<int, string> id => nombre, en su orden */
    public static function options(): array
    {
        return static::where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** "Otros": a donde va lo que no tiene categoría. */
    public static function otherId(): ?int
    {
        return static::where('name', 'Otros')->value('id');
    }
}
