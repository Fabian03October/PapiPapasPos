<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelidad:
 * - Cada nivel se repite N veces antes de subir (por defecto 1 = como hoy).
 * - Cada cliente lleva cuántas tarjetas ha llenado en su nivel.
 * - Bitácora de ajustes a mano (pasar tarjetas físicas a la digital).
 * Solo agrega; los clientes quedan con 0 tarjetas llenas y los niveles en 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_levels', function (Blueprint $table) {
            $table->unsignedSmallInteger('repeat_times')->default(1)->after('level_number');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('cards_completed')->default(0)->after('current_visits');
        });

        Schema::create('loyalty_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->json('before');   // {level, visits, cards}
            $table->json('after');
            $table->string('reward_given')->nullable(); // discount | gift
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_adjustments');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('cards_completed');
        });

        Schema::table('loyalty_levels', function (Blueprint $table) {
            $table->dropColumn('repeat_times');
        });
    }
};
