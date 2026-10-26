<?php

namespace App\Services;

use App\Models\BusinessSettings;
use App\Models\CashSession;
use App\Models\Sale;

/**
 * Arma el ticket y la comanda como renglones de texto ya acomodados para
 * una impresora térmica de 58mm (32 caracteres por línea), para mandarlos
 * por Bluetooth (public/js/impresora-bt.js los convierte a ESC/POS).
 *
 * Mismo contenido que las vistas tickets.venta / tickets.comanda que se
 * imprimen por Chrome - si se cambia una, hay que cambiar la otra.
 *
 * Cada renglón: ['text' => string, 'align' => left|center|right, 'bold' => bool, 'size' => normal|tall|big]
 * "tall" es doble alto (siguen cabiendo 32 columnas); "big" es doble alto
 * y doble ancho (solo caben 16).
 */
class ThermalTicketBuilder
{
    public const COLUMNS = 32;

    protected array $lines = [];

    public function forSale(Sale $sale, ?float $received = null): array
    {
        $sale->loadMissing(['items.product', 'items.variant', 'items.modifiers.modifier', 'customer', 'user']);
        $business = BusinessSettings::current();
        $this->lines = [];

        $this->text($business->name, 'center', bold: true, size: 'tall');
        foreach ([$business->address, $business->contact_info] as $info) {
            if ($info) {
                $this->text($info, 'center');
            }
        }
        $this->text('Ticket de venta', 'center');
        $this->separator();

        $this->text('Folio: #'.$sale->folio);
        $this->text('Fecha: '.$sale->created_at->format('d/m/Y H:i'));
        $this->text('Cajero: '.($sale->user->name ?? '-'));
        if ($sale->customer) {
            $this->text('Cliente: '.$sale->customer->name);
        }
        $this->separator();

        foreach ($sale->items as $item) {
            if ($item->cancelled_at) {
                continue;
            }

            // Ticket del cliente: solo lo que se cobra. El producto (con su
            // variante) y abajo las opciones de pago; las gratis (presentación,
            // aderezos, salsas) y las notas van solo en la comanda.
            $name = $item->qty.'x '.$item->product->name.($item->variant ? ' ('.$item->variant->name.')' : '');
            $this->row($name, $this->money($item->baseAmount()));

            foreach ($item->chargedModifiers() as $charged) {
                $this->row(' + '.$charged['label'], $this->money($charged['amount']));
            }

            if ($label = $item->manualDiscountLabel()) {
                $this->row(' '.$label, '-'.$this->money($item->manual_discount));
            }
        }
        $this->separator();

        $this->row('Subtotal', $this->money($sale->subtotal));
        if ($sale->discount_breakdown) {
            $productDiscounts = 0;
            foreach ($sale->discount_breakdown as $line) {
                if ($line['kind'] === 'manual_producto') {
                    $productDiscounts += $line['amount'];

                    continue;
                }
                $this->row($line['label'], '-'.$this->money($line['amount']));
            }
            if ($productDiscounts > 0) {
                $this->row('Desc. en productos', '-'.$this->money($productDiscounts));
            }
        } elseif ($sale->discount > 0) {
            $this->row('Descuento', '-'.$this->money($sale->discount));
        }
        $this->row('TOTAL', $this->money($sale->total), bold: true);

        if ($business->show_iva) {
            // Mismo cálculo que tickets.venta: 16% directo sobre el total.
            $ivaRate = (float) $business->iva_rate;
            $rateLabel = rtrim(rtrim(number_format($ivaRate, 2), '0'), '.');
            $this->text('(IVA incluido ('.$rateLabel.'%): '.$this->money($sale->total * $ivaRate / 100).')');
        }
        $this->separator();

        $this->text('Pago: '.Sale::paymentLabel($sale->payment_method));
        if ($sale->payment_method === 'efectivo' && ($paid = $sale->cashReceived($received)) !== null) {
            $this->row('Recibido', $this->money($paid));
            $this->row('Cambio', $this->money($paid - (float) $sale->total));
        }

        $this->text('');
        $this->text(
            $business->personalize_thank_you && $sale->customer
                ? '¡Gracias, '.$sale->customer->name.', por tu compra!'
                : (string) $business->thank_you_message,
            'center'
        );

        return $this->lines;
    }

    public function forKitchen(Sale $sale): array
    {
        $sale->loadMissing(['items.product', 'items.variant', 'items.modifiers.modifier.group', 'customer', 'correctsSale']);
        $this->lines = [];

        $this->text('COMANDA', 'center', bold: true, size: 'big');
        $this->text('#'.$sale->folio, 'center', bold: true, size: 'big');
        if ($sale->correctsSale) {
            $this->text('CORRECCION de #'.$sale->correctsSale->folio, 'center', bold: true, underline: true);
        }
        if ($sale->customer) {
            $this->text($sale->customer->name, 'center', bold: true, size: 'tall');
        }
        $this->text($sale->created_at->format('H:i'), 'center');
        $this->separator();

        foreach ($sale->items as $item) {
            if ($item->cancelled_at) {
                continue;
            }

            $this->text(
                $item->qty.'x '.$item->product->name.($item->variant ? ' ('.$item->variant->name.')' : ''),
                bold: true,
                size: 'tall'
            );

            // Orden del negocio (presentación, Queso, Mayonesa...); extras en
            // negrita y subrayado para que cocina no se los salte.
            foreach ($item->orderedModifiers() as $saleItemModifier) {
                $isExtra = $saleItemModifier->isExtra();
                $this->text('  + '.($isExtra ? 'EXTRA ' : '').$saleItemModifier->label(), bold: $isExtra, underline: $isExtra);
            }

            if ($item->notes) {
                $this->text('  NOTA: '.$item->notes, bold: true);
            }
        }

        return $this->lines;
    }

    /** Corte de caja (mismos números que tickets/cierre-caja, vía CorteCajaService). */
    public function forCashClose(CashSession $session): array
    {
        $c = (new CorteCajaService)->resumen($session);
        $this->lines = [];
        $diff = function (?float $value, string $label = '') {
            if ($value !== null && abs($value) >= 0.01) {
                $this->row(($value > 0 ? 'Sobra' : 'Falta').$label, $this->money(abs($value)), bold: true);
            }
        };

        $this->text(BusinessSettings::current()->name, 'center', bold: true, size: 'tall');
        $this->text('CORTE DE CAJA', 'center', bold: true);
        $this->separator();
        $this->text('Apertura: '.$session->opened_at->format('d/m/Y H:i'));
        $this->text('Cierre: '.($session->closed_at?->format('d/m/Y H:i') ?? '-'));
        $this->text('Abrió: '.($session->user?->name ?? '-'));
        $this->text('Cerró: '.($session->closedByUser?->name ?? '-'));
        $this->separator();

        $this->text('1. FONDO DE CAJA', bold: true);
        $this->row('Fondo inicial', $this->money($c['fondo']['esperado']));
        if ($c['separado']) {
            $this->row('Contado', $this->money($c['fondo']['contado']));
            $diff($c['fondo']['diferencia']);
        }
        $this->separator();

        $this->text('2. VENTAS DEL TURNO ('.$c['salesCount'].')', bold: true);
        foreach ($c['porMetodo'] as $metodo) {
            $this->row($metodo['label'].' ('.$metodo['count'].')', $this->money($metodo['total']));
        }
        $this->row('Total ventas', $this->money($c['ventasTotal']), bold: true);
        if ($c['deshechas'] > 0) {
            $this->row('Deshechas/corregidas', (string) $c['deshechas']);
        }
        $this->separator();

        $this->text('3. DINERO DE VENTAS (EFECTIVO)', bold: true);
        $this->row('Ventas en efectivo', $this->money($c['porMetodo']['efectivo']['total']));
        if ($c['ingresos'] > 0) {
            $this->row('+ Ingresos', $this->money($c['ingresos']));
        }
        if ($c['gastos'] > 0) {
            $this->row('- Gastos', $this->money($c['gastos']));
        }
        $this->row('Esperado', $this->money($c['ventasEfectivo']['esperado']));
        if ($c['separado']) {
            $this->row('Contado', $this->money($c['ventasEfectivo']['contado']));
            $diff($c['ventasEfectivo']['diferencia']);
        }
        $this->separator();

        $this->text('4. TOTAL EN CAJA', bold: true);
        $this->row('Fondo + ventas efectivo', $this->money($c['totalCaja']['esperado']));
        if ($c['totalCaja']['contado'] !== null) {
            $this->row('Contado', $this->money($c['totalCaja']['contado']), bold: true);
            $totalDiff = (float) $c['totalCaja']['diferencia'];
            $this->row(abs($totalDiff) < 0.01 ? 'Diferencia' : ($totalDiff > 0 ? 'Sobra' : 'Falta'), $this->money(abs($totalDiff)), bold: true);
        }
        if ($c['motivo']) {
            $this->text('Motivo: '.$c['motivo']);
        }

        if ($c['mermas']->isNotEmpty()) {
            $this->separator();
            $this->text('MERMAS DEL TURNO', bold: true);
            foreach ($c['mermas'] as $merma) {
                $this->row($merma->name, $this->money($merma->value));
            }
        }

        $this->text('');
        $this->text('');
        $this->separator();
        $this->text($session->closedByUser?->name ?? '-', 'center');
        $this->text('Firma de quien cerró la caja', 'center');

        return $this->lines;
    }

    /**
     * Texto libre, partido en varios renglones si no cabe.
     */
    protected function text(string $text, string $align = 'left', bool $bold = false, string $size = 'normal', bool $underline = false): void
    {
        $width = $size === 'big' ? intdiv(self::COLUMNS, 2) : self::COLUMNS;

        foreach ($this->wrap($this->clean($text), $width) as $chunk) {
            $line = ['text' => $chunk, 'align' => $align, 'bold' => $bold, 'size' => $size];
            if ($underline) {
                $line['underline'] = true;
            }
            $this->lines[] = $line;
        }
    }

    /**
     * Concepto a la izquierda y monto pegado a la derecha. Si el concepto no
     * cabe junto al monto, se parte y el monto va en el último renglón.
     */
    protected function row(string $left, string $right, bool $bold = false): void
    {
        $left = $this->clean($left);
        $right = $this->clean($right);
        $space = self::COLUMNS - mb_strlen($right) - 1;
        $chunks = $this->wrap($left, $space);
        $last = array_pop($chunks);

        foreach ($chunks as $chunk) {
            $this->lines[] = ['text' => $chunk, 'align' => 'left', 'bold' => $bold, 'size' => 'normal'];
        }

        $padding = max(1, self::COLUMNS - mb_strlen($last) - mb_strlen($right));
        $this->lines[] = ['text' => $last.str_repeat(' ', $padding).$right, 'align' => 'left', 'bold' => $bold, 'size' => 'normal'];
    }

    protected function separator(): void
    {
        $this->lines[] = ['text' => str_repeat('-', self::COLUMNS), 'align' => 'left', 'bold' => false, 'size' => 'normal'];
    }

    protected function money(float|string $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    /**
     * La impresora solo entiende Latin-1 (acentos y ñ sí; emojis, comillas
     * "curvas" y demás no): se cambian por su equivalente o se quitan, para
     * que no salgan signos raros en el papel.
     */
    protected function clean(string $text): string
    {
        $text = strtr($text, [
            '‘' => "'", '’' => "'", '“' => '"', '”' => '"', '–' => '-', '—' => '-',
            '…' => '...', '•' => '-', '·' => '-', "\t" => ' ', "\r" => '', "\n" => ' ',
        ]);

        // Solo rtrim: los espacios del inicio son la sangría de extras/descuentos.
        return rtrim(preg_replace('/[^\x{20}-\x{7E}\x{A1}-\x{FF}]/u', '', $text));
    }

    /**
     * wordwrap() que respeta acentos (cuenta caracteres, no bytes) y parte
     * palabras más largas que el renglón.
     *
     * @return string[]
     */
    protected function wrap(string $text, int $width): array
    {
        if (trim($text) === '') {
            return [''];
        }

        // La sangría se repite en cada renglón partido.
        $indent = str_repeat(' ', mb_strlen($text) - mb_strlen(ltrim($text)));
        if ($indent !== '') {
            return array_map(fn ($line) => $indent.$line, $this->wrap(ltrim($text), $width - mb_strlen($indent)));
        }

        $lines = [];
        $current = '';

        foreach (preg_split('/ +/', $text) as $word) {
            while (mb_strlen($word) > $width) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }

            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) <= $width) {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        $lines[] = $current;

        return $lines;
    }
}
