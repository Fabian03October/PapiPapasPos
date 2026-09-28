<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_card_settings', function (Blueprint $table) {
            $table->text('contact_info')->nullable()->after('hex_background_color');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_card_settings', function (Blueprint $table) {
            $table->dropColumn('contact_info');
        });
    }
};
