<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_card_milestones', function (Blueprint $table) {
            $table->enum('reward_type', ['discount_percent', 'free_product', 'custom'])->default('custom');
            $table->decimal('reward_value', 10, 2)->nullable();
            $table->foreignId('reward_product_id')->nullable()->constrained('products');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_card_milestones', function (Blueprint $table) {
            $table->dropForeign(['reward_product_id']);
            $table->dropColumn(['reward_type', 'reward_value', 'reward_product_id']);
        });
    }
};