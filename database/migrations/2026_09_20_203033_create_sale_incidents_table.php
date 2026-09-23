<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained();
            $table->enum('type', ['cancelacion', 'reposicion']);
            $table->text('reason');
            $table->enum('status', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('authorized_by')->nullable()->constrained('users');
            $table->enum('authorization_method', ['pin', 'codigo_temporal', 'revision_posterior'])->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_incidents');
    }
};