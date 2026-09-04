<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items');
            $table->decimal('discount_applied', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['sale_item_id']);
            $table->dropColumn(['sale_id', 'sale_item_id', 'discount_applied']);
        });
    }
};