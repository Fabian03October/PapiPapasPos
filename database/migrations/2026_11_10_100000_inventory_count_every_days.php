<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El conteo de inventario al cerrar caja pasa de "sí/no" a "cada cuánto"
 * (lo escoge el manager): 1 = en cada cierre, N = si no se ha contado en N
 * días, 0 = nunca (solo cuando él quiera).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('inventory_count_every_days')->default(1);
        });

        DB::table('business_settings')->where('require_inventory_count_on_close', false)->update(['inventory_count_every_days' => 0]);

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('require_inventory_count_on_close');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('require_inventory_count_on_close')->default(true);
        });

        DB::table('business_settings')->where('inventory_count_every_days', 0)->update(['require_inventory_count_on_close' => false]);

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('inventory_count_every_days');
        });
    }
};
