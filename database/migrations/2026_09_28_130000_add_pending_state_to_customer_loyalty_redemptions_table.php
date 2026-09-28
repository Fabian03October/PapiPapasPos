<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Filas viejas (si las hay) siempre se redimieron en el momento -
        // se marcan como ya canjeadas, con earned_at = redeemed_at, antes
        // de que earned_at se vuelva NOT NULL más abajo.
        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'redeemed'])->default('pending')->after('type');
            $table->timestamp('earned_at')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('earned_at');
            $table->foreignId('redeemed_sale_id')->nullable()->after('sale_id')->constrained('sales');
        });

        DB::table('customer_loyalty_redemptions')->update([
            'status' => 'redeemed',
            'earned_at' => DB::raw('redeemed_at'),
            'redeemed_sale_id' => DB::raw('sale_id'),
        ]);

        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->timestamp('earned_at')->nullable(false)->change();
            $table->timestamp('redeemed_at')->nullable()->change();
            // Ya era conceptualmente "sin valor" cuando no había descuento
            // (el default de 0 nunca se usaba - el código siempre mandaba
            // null en ese caso), pero la columna no era nullable. Un
            // premio pendiente sin canjear no tiene descuento que guardar.
            $table->decimal('discount_applied', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->timestamp('redeemed_at')->nullable(false)->change();
            $table->decimal('discount_applied', 10, 2)->nullable(false)->default(0)->change();
            $table->dropForeign(['redeemed_sale_id']);
            $table->dropColumn(['status', 'earned_at', 'expires_at', 'redeemed_sale_id']);
        });
    }
};
