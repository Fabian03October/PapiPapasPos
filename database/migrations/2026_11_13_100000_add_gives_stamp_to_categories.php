<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sellos solo con papas u órdenes: cada categoría dice si da sello. Bebidas y
 * Extras sueltos no dan (el manager lo cambia en Catálogo → Categorías).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('gives_stamp')->default(true);
        });

        DB::table('categories')->whereIn(DB::raw('LOWER(TRIM(name))'), ['bebidas', 'extras'])->update(['gives_stamp' => false]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('gives_stamp');
        });
    }
};
