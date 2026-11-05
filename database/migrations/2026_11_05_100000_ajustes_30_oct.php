<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes del 30 de octubre:
 * - Bitácora de cambios del panel (quién creó, editó o borró qué).
 * - El manager decide si el conteo de inventario es obligatorio al cerrar caja,
 *   y se puede contar a cualquier hora (esos conteos no son de un turno).
 * - Mensaje de WhatsApp con el enlace de Mi tarjeta, editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 10); // created | updated | deleted
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('label')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('require_inventory_count_on_close')->default(true);
            $table->text('whatsapp_card_message')->nullable();
        });

        Schema::table('inventory_counts', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['require_inventory_count_on_close', 'whatsapp_card_message']);
        });
    }
};
