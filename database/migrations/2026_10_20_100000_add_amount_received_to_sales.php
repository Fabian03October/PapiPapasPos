<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Con cuánto pagó el cliente en efectivo. Antes solo viajaba a la impresora
 * Bluetooth al momento de cobrar, así que reimpresiones, Chrome y la
 * estación de impresión no podían mostrar "Recibido / Cambio". Solo AGREGA
 * una columna vacía; no toca ventas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount_received', 10, 2)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('amount_received');
        });
    }
};
