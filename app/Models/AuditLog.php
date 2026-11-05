<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bitácora de cambios hechos desde el panel (ver AppServiceProvider). */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = ['created' => 'Creó', 'updated' => 'Editó', 'deleted' => 'Borró'];

    /** Nunca se guardan en la bitácora. */
    public const SECRET = ['pin', 'password', 'remember_token', 'code', 'qr_code', 'google_wallet_object_id', 'auth_token'];

    protected $fillable = ['user_id', 'action', 'auditable_type', 'auditable_id', 'label', 'changes'];

    protected $casts = ['changes' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Model $model, string $action): void
    {
        $skip = [...self::SECRET, ...$model->getHidden(), 'created_at', 'updated_at'];

        $changes = match ($action) {
            'updated' => collect($model->getChanges())->except($skip)
                ->map(fn ($new, $key) => ['antes' => $model->getOriginal($key), 'despues' => $new])->all(),
            default => collect($model->getAttributes())->except($skip)->all(),
        };

        if ($action === 'updated' && $changes === []) {
            return;
        }

        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'label' => $model->getAttribute('name') ?? $model->getAttribute('title') ?? '#'.$model->getKey(),
            'changes' => $changes,
        ]);
    }

    /** "Producto", "Insumo"... para la tabla. */
    public function modelName(): string
    {
        return match (class_basename($this->auditable_type)) {
            'Product' => 'Producto',
            'ProductVariant' => 'Variante',
            'RecipeItem' => 'Receta',
            'Ingredient' => 'Insumo',
            'InventoryMovement' => 'Movimiento de inventario',
            'Category' => 'Categoría',
            'ModifierGroup' => 'Grupo de extras',
            'Modifier' => 'Extra / opción',
            'ModifierRecipeItem' => 'Receta de extra',
            'Promotion' => 'Promoción',
            'Customer' => 'Cliente',
            'LoyaltyLevel' => 'Nivel',
            'User' => 'Usuario',
            'BusinessSettings' => 'Datos del negocio',
            'DayPass' => 'Cupón',
            'ExpenseCategory' => 'Categoría de gasto',
            'GeneralMovement' => 'Gasto / ingreso general',
            default => class_basename($this->auditable_type),
        };
    }
}
