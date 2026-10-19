<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS v2 (cobro estilo OaxacaWare + pedidos del cliente). Solo AGREGA:
 * columnas nuevas (todas con valor por defecto o vacías) y "transferencia"
 * a la lista de métodos de pago. No modifica ni borra datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Orden de aderezos/salsas dentro de su grupo (Queso, Mayonesa,
        // Kétchup...): se respeta en la venta y en la comanda.
        Schema::table('modifiers', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('price_delta');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->date('birthdate')->nullable()->after('phone');
        });

        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('efectivo', 'tarjeta', 'transferencia') NOT NULL");

        // Deshacer / corregir una venta: queda como venta cancelada para las
        // cuentas, pero se ve como "Deshecha" o "Corregida → #0013".
        Schema::table('sales', function (Blueprint $table) {
            $table->string('revert_type')->nullable()->after('status'); // deshecha | corregida
            $table->timestamp('reverted_at')->nullable()->after('revert_type');
            $table->foreignId('reverted_by')->nullable()->after('reverted_at')->constrained('users');
            $table->string('revert_reason')->nullable()->after('reverted_by');
            $table->unsignedBigInteger('corrects_sale_id')->nullable()->after('revert_reason');
            $table->unsignedBigInteger('replaced_by_sale_id')->nullable()->after('corrects_sale_id');
        });

        // Pausa entre ticket y comanda para no saturar la impresora térmica.
        Schema::table('business_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('print_delay_seconds')->default(6);
        });

        // Cierre con DOS conteos: el fondo (que se queda para el siguiente
        // turno) y el dinero de las ventas (el que se entrega). Las columnas
        // de siempre (expected/counted/difference) siguen siendo el total.
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->decimal('fund_counted_amount', 10, 2)->nullable()->after('difference');
            $table->decimal('fund_difference', 10, 2)->nullable()->after('fund_counted_amount');
            $table->decimal('sales_expected_amount', 10, 2)->nullable()->after('fund_difference');
            $table->decimal('sales_counted_amount', 10, 2)->nullable()->after('sales_expected_amount');
            $table->decimal('sales_difference', 10, 2)->nullable()->after('sales_counted_amount');
            $table->string('difference_reason')->nullable()->after('sales_difference');
        });

        Schema::table('cash_session_denominations', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('cash_session_id'); // fondo | ventas
        });
    }

    public function down(): void
    {
        Schema::table('cash_session_denominations', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn(['fund_counted_amount', 'fund_difference', 'sales_expected_amount', 'sales_counted_amount', 'sales_difference', 'difference_reason']);
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('print_delay_seconds');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverted_by');
            $table->dropColumn(['revert_type', 'reverted_at', 'revert_reason', 'corrects_sale_id', 'replaced_by_sale_id']);
        });

        DB::statement("UPDATE sales SET payment_method = 'tarjeta' WHERE payment_method = 'transferencia'");
        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('efectivo', 'tarjeta') NOT NULL");

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('birthdate');
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
