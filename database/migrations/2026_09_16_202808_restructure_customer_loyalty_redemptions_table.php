<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->dropForeign(['customer_loyalty_card_id']);
            $table->dropForeign(['milestone_id']);
            $table->dropColumn(['customer_loyalty_card_id', 'milestone_id']);
        });

        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->foreignId('customer_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_level_id')->after('customer_id')->constrained('loyalty_levels');
            $table->enum('type', ['discount', 'gift'])->after('loyalty_level_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['loyalty_level_id']);
            $table->dropColumn(['customer_id', 'loyalty_level_id', 'type']);
        });

        Schema::table('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->foreignId('customer_loyalty_card_id')->constrained('customer_loyalty_cards')->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('loyalty_card_milestones');
        });
    }
};