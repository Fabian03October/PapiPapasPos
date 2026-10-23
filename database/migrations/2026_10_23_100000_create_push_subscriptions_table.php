<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos (Web Push) de "Mi tarjeta": cada celular que aceptó notificaciones
 * entrega una "dirección" (endpoint + llaves) ligada a un cliente. Un cliente
 * puede tener varios celulares. Tabla nueva; no toca datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique(); // el endpoint es largo: se indexa su sha256
            $table->string('public_key');                // p256dh
            $table->string('auth_token');                // auth
            $table->string('user_agent')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
