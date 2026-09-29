<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * modifiers, product_variants y loyalty_levels tienen ventas/canjes que
 * los referencian sin cascada (borrar tronaba con un error de MySQL).
 * customers y modifier_groups sí tenían cascada, pero borrarlos destruía
 * en silencio el historial de lealtad/modificadores. En los 5 casos la
 * solución es la misma: desactivar en vez de borrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('price_delta');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('price_delta');
        });

        Schema::table('loyalty_levels', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('gift_description');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('current_visits');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('modifiers', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('loyalty_levels', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('modifier_groups', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
