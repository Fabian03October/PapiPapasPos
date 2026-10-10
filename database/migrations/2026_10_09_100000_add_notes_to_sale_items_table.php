<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Notas para cocina" (ej. "sin cebolla") que el cajero escribe al agregar
 * un producto: antes se capturaban en pantalla pero nunca se guardaban, así
 * que no llegaban a la comanda. Solo agrega una columna nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('notes')->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
