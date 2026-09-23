<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\ManagerAuthorizationCode;
use App\Models\ModifierRecipeItem;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Models\SaleIncident;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class SaleIncidentService
{
    public function redeemTemporaryCode(string $code): ?User
    {
        $candidates = ManagerAuthorizationCode::whereNull('used_at')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($code, $candidate->code)) {
                $candidate->update(['used_at' => now()]);

                return $candidate->manager;
            }
        }

        return null;
    }

    public function createPending(array $saleItemIds, string $type, string $reason, User $requestedBy): SaleIncident
    {
        $incident = SaleIncident::create([
            'sale_id' => SaleItem::whereIn('id', $saleItemIds)->value('sale_id'),
            'type' => $type,
            'reason' => $reason,
            'status' => 'pendiente',
            'requested_by' => $requestedBy->id,
        ]);

        $incident->items()->sync($saleItemIds);

        return $incident;
    }

    public function createAndApprove(array $saleItemIds, string $type, string $reason, User $requestedBy, User $manager): SaleIncident
    {
        $incident = SaleIncident::create([
            'sale_id' => SaleItem::whereIn('id', $saleItemIds)->value('sale_id'),
            'type' => $type,
            'reason' => $reason,
            'status' => 'aprobada',
            'requested_by' => $requestedBy->id,
            'authorized_by' => $manager->id,
            'authorization_method' => 'codigo_temporal',
            'authorized_at' => now(),
        ]);

        $incident->items()->sync($saleItemIds);
        $this->applyEffects($incident);

        return $incident;
    }

    public function approve(SaleIncident $incident, User $manager): void
    {
        $incident->update([
            'status' => 'aprobada',
            'authorized_by' => $manager->id,
            'authorization_method' => 'revision_posterior',
            'authorized_at' => now(),
        ]);

        $this->applyEffects($incident);
    }

    public function reject(SaleIncident $incident, User $manager): void
    {
        $incident->update([
            'status' => 'rechazada',
            'authorized_by' => $manager->id,
            'authorization_method' => 'revision_posterior',
            'authorized_at' => now(),
        ]);
    }

    protected function applyEffects(SaleIncident $incident): void
    {
        $items = $incident->items;

        if ($incident->type === 'cancelacion') {
            $this->applyCancelacion($incident, $items);
        } else {
            $this->applyReposicion($incident, $items);
        }
    }

    protected function applyCancelacion(SaleIncident $incident, Collection $items): void
    {
        $cancelledTotal = 0;

        foreach ($items as $item) {
            foreach ($this->ingredientConsumptionForItem($item) as $ingredientId => $qty) {
                $this->registerMovement($ingredientId, 'ajuste', $qty, $incident);
                Ingredient::where('id', $ingredientId)->increment('stock_qty', $qty);
            }

            $item->update(['cancelled_at' => now()]);
            $cancelledTotal += $item->line_total;
        }

        $sale = Sale::find($incident->sale_id);
        $sale->subtotal = max(0, $sale->subtotal - $cancelledTotal);
        $sale->total = max(0, $sale->total - $cancelledTotal);

        if ($sale->items()->whereNull('cancelled_at')->count() === 0) {
            $sale->status = 'cancelada';
        }

        $sale->save();
    }

    protected function applyReposicion(SaleIncident $incident, Collection $items): void
    {
        foreach ($items as $item) {
            foreach ($this->ingredientConsumptionForItem($item) as $ingredientId => $qty) {
                $this->registerMovement($ingredientId, 'merma', -$qty, $incident);
                Ingredient::where('id', $ingredientId)->decrement('stock_qty', $qty);
            }
        }
    }

    protected function registerMovement(int $ingredientId, string $type, float $qty, SaleIncident $incident): void
    {
        InventoryMovement::create([
            'ingredient_id' => $ingredientId,
            'type' => $type,
            'qty' => $qty,
            'unit_cost' => Ingredient::find($ingredientId)?->cost_per_unit,
            'reference_type' => 'sale_incident',
            'reference_id' => $incident->id,
            'user_id' => $incident->authorized_by,
        ]);
    }

    /**
     * Recalcula qué insumos y cuánto consumió ESTE producto específico
     * (igual que al vender), para poder revertirlo o volverlo a gastar.
     */
    protected function ingredientConsumptionForItem(SaleItem $item): array
    {
        $consumption = [];

        $baseRecipe = RecipeItem::where('product_id', $item->product_id)->whereNull('variant_id')->get();
        foreach ($baseRecipe as $r) {
            $consumption[$r->ingredient_id] = ($consumption[$r->ingredient_id] ?? 0) + $r->qty * $item->qty;
        }

        if ($item->variant_id) {
            $variantRecipe = RecipeItem::where('product_id', $item->product_id)->where('variant_id', $item->variant_id)->get();
            foreach ($variantRecipe as $r) {
                $consumption[$r->ingredient_id] = ($consumption[$r->ingredient_id] ?? 0) + $r->qty * $item->qty;
            }
        }

        foreach ($item->modifiers as $saleItemModifier) {
            $modifierRecipe = ModifierRecipeItem::where('modifier_id', $saleItemModifier->modifier_id)->get();
            foreach ($modifierRecipe as $r) {
                $consumption[$r->ingredient_id] = ($consumption[$r->ingredient_id] ?? 0) + $r->qty * $item->qty;
            }
        }

        return $consumption;
    }
}