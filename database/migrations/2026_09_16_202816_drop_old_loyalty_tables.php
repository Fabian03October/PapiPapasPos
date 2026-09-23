<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orden importa por las llaves foráneas: primero la tabla puente,
        // luego los milestones, y al final la tabla de tarjetas.
        Schema::dropIfExists('customer_loyalty_cards');
        Schema::dropIfExists('loyalty_card_milestones');
        Schema::dropIfExists('loyalty_cards');
    }

    public function down(): void
    {
        // Restructura de un solo sentido: si necesitas revertir, corre
        // `php artisan migrate:rollback` sobre el commit anterior en vez de esto.
    }
};