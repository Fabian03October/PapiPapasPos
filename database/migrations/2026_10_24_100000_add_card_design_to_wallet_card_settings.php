<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseño de "Mi tarjeta" que el negocio elige en el panel (colores, logo,
 * sello, textos). Columnas nuevas con valores vacíos: si no se llenan, la
 * tarjeta se ve como hoy (negro y amarillo, sello 🍟). No toca datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_card_settings', function (Blueprint $table) {
            $table->string('card_bg_color', 7)->nullable();
            $table->string('card_accent_color', 7)->nullable();
            $table->string('card_logo_path')->nullable();
            $table->string('card_title', 40)->nullable();
            $table->string('stamp_emoji', 16)->nullable();
            $table->string('stamp_image_path')->nullable();
            $table->string('discount_icon', 16)->nullable();
            $table->string('gift_icon', 16)->nullable();
            $table->string('welcome_message', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_card_settings', function (Blueprint $table) {
            $table->dropColumn(['card_bg_color', 'card_accent_color', 'card_logo_path', 'card_title', 'stamp_emoji',
                'stamp_image_path', 'discount_icon', 'gift_icon', 'welcome_message']);
        });
    }
};
