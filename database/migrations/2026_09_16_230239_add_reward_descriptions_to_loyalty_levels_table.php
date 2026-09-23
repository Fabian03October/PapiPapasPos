<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_levels', function (Blueprint $table) {
            $table->string('discount_description')->nullable()->after('discount_percent');
            $table->string('gift_description')->nullable()->after('free_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_levels', function (Blueprint $table) {
            $table->dropColumn(['discount_description', 'gift_description']);
        });
    }
};