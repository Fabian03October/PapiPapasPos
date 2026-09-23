<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('level_number')->unique();
            // Premio de la visita 4 (fija en todos los niveles)
            $table->decimal('discount_percent', 5, 2);
            // Premio de la visita 8 (fija en todos los niveles)
            $table->foreignId('free_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_levels');
    }
};