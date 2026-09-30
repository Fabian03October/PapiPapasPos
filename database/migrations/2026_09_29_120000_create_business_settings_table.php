<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del negocio para el ticket impreso: dirección, contacto, si se
 * desglosa el IVA (los precios ya lo incluyen, solo se muestra informativo,
 * el total no cambia) y el mensaje de agradecimiento - el manager decide
 * todo esto desde el panel, sin tocar código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_info')->nullable();
            $table->boolean('show_iva')->default(true);
            $table->decimal('iva_rate', 5, 2)->default(16.00);
            $table->string('thank_you_message')->nullable();
            $table->boolean('personalize_thank_you')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
