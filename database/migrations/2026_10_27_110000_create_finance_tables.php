<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gastos e ingresos:
 * - Categorías (Renta, Servicios, Insumos, Nómina, Mantenimiento, Otros; el
 *   manager agrega más).
 * - Gastos/ingresos GENERALES: lo que no sale de la caja (renta, luz...).
 * - Categoría opcional en los movimientos de caja del turno.
 * Solo agrega; los movimientos de caja existentes quedan sin categoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('expense_categories')->insert(collect(['Renta', 'Servicios', 'Insumos', 'Nómina', 'Mantenimiento', 'Otros'])
            ->map(fn ($name, $i) => ['name' => $name, 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            ->all());

        Schema::create('general_movements', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['ingreso', 'gasto']);
            $table->decimal('amount', 10, 2);
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->date('occurred_on');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->index(['occurred_on', 'type']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('reason')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_category_id');
        });
        Schema::dropIfExists('general_movements');
        Schema::dropIfExists('expense_categories');
    }
};
