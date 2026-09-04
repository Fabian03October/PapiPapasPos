<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loyalty_cards', function (Blueprint $table) {
            $table->dropUnique(['qr_code']);
            $table->dropColumn('qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('customer_loyalty_cards', function (Blueprint $table) {
            $table->string('qr_code')->unique();
        });
    }
};