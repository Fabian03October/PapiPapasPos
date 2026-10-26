<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extras, aderezos y salsas repetidos ("Queso ×2", "Extra queso ×3"): cuántas
 * veces se pidió cada opción en un producto. Las ventas existentes quedan en
 * 1, que es lo que fueron. Solo agrega una columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_item_modifiers', function (Blueprint $table) {
            $table->unsignedSmallInteger('qty')->default(1)->after('modifier_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_item_modifiers', function (Blueprint $table) {
            $table->dropColumn('qty');
        });
    }
};
