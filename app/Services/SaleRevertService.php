<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Deshacer venta" (como en OaxacaWare, más la "edición" que pidió el
 * cliente): la venta se deshace y su orden regresa al carrito para
 * corregirla y volver a cobrarla.
 *
 * - Para las cuentas es una cancelación completa: regresa el inventario y la
 *   venta queda en $0 (el corte no se descuadra). Queda la incidencia con el
 *   motivo y quién la deshizo.
 * - Se ve como "Deshecha" (o "Corregida → #0013" al cobrar la nueva), no
 *   como "Cancelada".
 * - Se le quita al cliente la visita de lealtad (y el premio) de esa venta.
 *
 * Reglas: el cajero solo SUS ventas y los primeros UNDO_MINUTES minutos;
 * manager y gerente cualquier venta del turno abierto. Siempre del turno
 * abierto (un corte ya entregado no se toca).
 */
class SaleRevertService
{
    public const UNDO_MINUTES = 5;

    /** null si se puede deshacer; si no, el motivo para mostrárselo al cajero. */
    public function whyNot(Sale $sale, User $user): ?string
    {
        if ($sale->isReverted()) {
            return 'Esa venta ya se había deshecho.';
        }

        if ($sale->status !== 'pagada' || $sale->items()->whereNotNull('cancelled_at')->exists()) {
            return 'Esa venta ya tiene cancelaciones; revísala en Ventas del turno.';
        }

        if ($sale->cashSession?->closed_at !== null) {
            return 'Esa venta es de un turno que ya se cerró.';
        }

        if (! $user->puedeAutorizar()) {
            if ((int) $sale->user_id !== (int) $user->id) {
                return 'Solo puedes deshacer tus propias ventas. Pídeselo al manager o gerente.';
            }

            if ($sale->created_at->lt(now()->subMinutes(self::UNDO_MINUTES))) {
                return 'Ya pasaron más de '.self::UNDO_MINUTES.' minutos. Pídele al manager o gerente que la deshaga, o cancélala con código.';
            }
        }

        return null;
    }

    /**
     * Deshace la venta y regresa la orden (en el formato del carrito de la
     * pantalla de venta) para corregirla.
     */
    public function revert(Sale $sale, User $user, string $reason): array
    {
        return DB::transaction(function () use ($sale, $user, $reason) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->first();
            $sale->load(['items.product', 'items.variant', 'items.modifiers.modifier.group', 'customer']);

            $order = $this->orderFor($sale);

            (new SaleIncidentService)->createAndApprove(
                $sale->items->pluck('id')->all(), 'cancelacion', 'Venta deshecha: '.$reason, $user, $user, 'pin'
            );

            $sale->refresh()->update([
                'revert_type' => 'deshecha',
                'reverted_at' => now(),
                'reverted_by' => $user->id,
                'revert_reason' => $reason,
                'printed_at' => $sale->printed_at ?? now(), // que ninguna estación la imprima
            ]);

            if ($sale->customer) {
                $this->revertLoyalty($sale);
            }

            return $order;
        });
    }

    /**
     * La venta nueva corrige a la deshecha: la original queda "Corregida".
     */
    public function linkCorrection(Sale $original, Sale $correction): void
    {
        $original->update(['revert_type' => 'corregida', 'replaced_by_sale_id' => $correction->id]);
        $correction->update(['corrects_sale_id' => $original->id]);
    }

    /** La orden en el mismo formato que el carrito de venta/index. */
    protected function orderFor(Sale $sale): array
    {
        $saleDiscount = collect($sale->discount_breakdown ?? [])->firstWhere('kind', 'manual_venta');

        return [
            'sale_id' => $sale->id,
            'folio' => $sale->folio,
            'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name] : null,
            'sale_discount' => $saleDiscount ? $this->discountFromLabel($saleDiscount) : null,
            'items' => $sale->items->map(function ($item) {
                $modifiers = $item->orderedModifiers();

                return [
                    'productId' => $item->product_id,
                    'name' => $item->product->name ?? 'Producto',
                    'variantId' => $item->variant_id,
                    'variantName' => $item->variant?->name,
                    'modifierIds' => $modifiers->pluck('modifier_id')->values()->all(),
                    'modifierNames' => $modifiers->map(fn ($m) => $m->modifier->name)->values()->all(),
                    'notes' => (string) $item->notes,
                    'qty' => (int) $item->qty,
                    'unitPrice' => (float) $item->unit_price,
                    'discount' => $item->manual_discount_type ? [
                        'type' => $item->manual_discount_type,
                        'value' => $item->manual_discount_value !== null ? (float) $item->manual_discount_value : null,
                        'reason' => (string) $item->manual_discount_reason,
                    ] : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * El descuento a la venta solo se guardó como renglón del desglose
     * ("Desc. 10% (Cliente frecuente)"): se reconstruye de ahí.
     */
    protected function discountFromLabel(array $line): ?array
    {
        if (! preg_match('/^(Cortesía|Desc\. \$?([\d.,]+)(%)?)(?: \((.*)\))?$/u', $line['label'], $m)) {
            return null;
        }

        $reason = $m[4] ?? 'Corrección';

        if ($m[1] === 'Cortesía') {
            return ['type' => 'courtesy', 'value' => null, 'reason' => $reason];
        }

        return [
            'type' => ($m[3] ?? '') === '%' ? 'percent' : 'amount',
            'value' => (float) str_replace(',', '', $m[2]),
            'reason' => $reason,
        ];
    }

    /**
     * Quita la visita (y el premio) que el cliente ganó con esta venta, y
     * regresa a "pendiente" un premio de antes que se haya canjeado en ella.
     */
    protected function revertLoyalty(Sale $sale): void
    {
        // Con candado: otra tablet podría estar cobrándole al mismo cliente.
        $customer = Customer::whereKey($sale->customer_id)->lockForUpdate()->first();

        if (! $customer) {
            return;
        }

        // Un premio viejo que se cobró en esta venta vuelve a quedar por
        // cobrar. Uno por uno (no update masivo) para que la tarjeta de
        // Wallet se entere del cambio.
        CustomerLoyaltyRedemption::where('redeemed_sale_id', $sale->id)
            // NULL != X no es verdadero en SQL: un premio sin venta de origen
            // (dado a mano) también debe volver a quedar por cobrar.
            ->where(fn ($q) => $q->whereNull('sale_id')->orWhere('sale_id', '!=', $sale->id))
            ->get()
            ->each->update(['status' => 'pending', 'redeemed_at' => null, 'redeemed_sale_id' => null, 'discount_applied' => null]);

        $earned = CustomerLoyaltyRedemption::with('loyaltyLevel')->where('sale_id', $sale->id)->first();

        // El premio que ganó esta venta ya se cobró en OTRA venta: el cliente
        // ya lo disfrutó. No se le quita la visita ni el premio (regresarle
        // el nivel le dejaría ganar ese mismo premio dos veces).
        if ($earned && $earned->status === 'redeemed' && $earned->redeemed_sale_id && $earned->redeemed_sale_id !== $sale->id) {
            return;
        }

        if ($customer->current_visits > 0) {
            $customer->update(['current_visits' => $customer->current_visits - 1]);
        } elseif ($earned && $earned->type === 'gift') {
            // Esa visita era la que completaba el nivel (visita 8): se regresa
            // al nivel de antes, con 7 visitas.
            $customer->update([
                'current_level' => $earned->loyaltyLevel?->level_number ?? $customer->current_level,
                'current_visits' => LoyaltyService::VISITS_FOR_GIFT - 1,
            ]);
        }

        $earned?->delete();
    }
}
