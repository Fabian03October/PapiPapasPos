<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percent_off_sale', 'fixed_price_combo', 'fixed_price_single') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percent_off_sale', 'fixed_price_combo') NOT NULL");
    }
};