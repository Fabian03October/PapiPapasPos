<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('current_level')->default(1)->after('qr_code');
            // 0 a 8: al llegar a 4 se desbloquea el descuento, al llegar a 8 el regalo y sube de nivel
            $table->unsignedTinyInteger('current_visits')->default(0)->after('current_level');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['current_level', 'current_visits']);
        });
    }
};