<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_loyalty_card_id')->constrained('customer_loyalty_cards')->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('loyalty_card_milestones');
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_redemptions');
    }
};