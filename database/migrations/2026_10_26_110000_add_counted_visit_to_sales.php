<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Un sello al día": cada venta recuerda si le sumó la visita (sello) al
 * cliente. Las ventas anteriores con cliente sí la sumaron (así funcionaba),
 * se marcan para que el día del cambio nadie gane un sello doble.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('counted_visit')->default(false)->after('customer_id');
            $table->index(['customer_id', 'counted_visit', 'created_at'], 'sales_visita_del_dia');
        });

        DB::table('sales')->whereNotNull('customer_id')->update(['counted_visit' => true]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_visita_del_dia');
            $table->dropColumn('counted_visit');
        });
    }
};
