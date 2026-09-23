<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // manager que lo generó
            $table->string('code'); // hash del código de 6 dígitos
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_authorization_codes');
    }
};