<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descuentos y cortesías que el cajero aplica a mano en el carrito (a un
 * producto o a toda la venta), aparte de las promociones automáticas y la
 * fidelidad. Todo queda guardado con su motivo para poder auditarlo después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('manual_discount', 10, 2)->default(0)->after('line_total');
            $table->string('manual_discount_type')->nullable()->after('manual_discount'); // percent | amount | courtesy
            $table->decimal('manual_discount_value', 10, 2)->nullable()->after('manual_discount_type');
            $table->string('manual_discount_reason')->nullable()->after('manual_discount_value');
        });

        Schema::table('sales', function (Blueprint $table) {
            // Suma de todos los descuentos manuales (por producto + a la
            // venta). "discount" sigue siendo el total de TODOS los descuentos.
            $table->decimal('manual_discount', 10, 2)->default(0)->after('discount');
            // Desglose línea por línea de qué descuentos se aplicaron y por qué.
            $table->json('discount_breakdown')->nullable()->after('manual_discount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['manual_discount', 'manual_discount_type', 'manual_discount_value', 'manual_discount_reason']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['manual_discount', 'discount_breakdown']);
        });
    }
};
