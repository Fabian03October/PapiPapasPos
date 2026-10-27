<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pase del día: código de un solo uso que el manager le da (en papel) a
 * alguien que no está en las tarjetas de fidelidad. Da un producto gratis,
 * un % o un $ de descuento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_passes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->enum('benefit_type', ['product', 'percent', 'amount']);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('value', 10, 2)->nullable(); // % o $
            $table->date('expires_on');                   // vale todo ese día
            $table->string('note')->nullable();           // para quién
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('day_pass_id')->nullable()->after('customer_id')->constrained('day_passes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('day_pass_id');
        });
        Schema::dropIfExists('day_passes');
    }
};
