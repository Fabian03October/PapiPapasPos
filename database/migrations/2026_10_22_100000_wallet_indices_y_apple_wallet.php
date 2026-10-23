<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelidad / Wallet. Solo AGREGA (índices y tablas nuevas); no toca datos.
 *
 * - customers.phone único: dos clientes con el mismo teléfono terminaban
 *   compartiendo tarjeta. Revisado antes: producción no tiene repetidos.
 * - Índice para "¿tiene premio por cobrar?" (se consulta en cada venta con
 *   cliente y en cada actualización de la tarjeta).
 * - Apple Wallet: un pase por cliente y los iPhone registrados para recibir
 *   sus actualizaciones (protocolo "Wallet Web Service" de Apple).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('phone');
        });

        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->index(['customer_id', 'status', 'expires_at'], 'redemptions_pending_lookup');
        });

        Schema::create('apple_wallet_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('serial_number')->unique();       // = customers.qr_code
            $table->string('authentication_token', 64);      // secreto que el iPhone manda de vuelta
            $table->timestamps();                            // updated_at = "última modificación" del pase
        });

        Schema::create('apple_wallet_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apple_wallet_pass_id')->constrained()->cascadeOnDelete();
            $table->string('device_library_identifier');
            $table->string('push_token');
            $table->timestamps();
            $table->unique(['apple_wallet_pass_id', 'device_library_identifier'], 'apple_wallet_reg_unique');
            $table->index('device_library_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_wallet_registrations');
        Schema::dropIfExists('apple_wallet_passes');

        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->dropIndex('redemptions_pending_lookup');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
