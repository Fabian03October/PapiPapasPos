<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mes-día del cumpleaños ("03-14"), calculado por MySQL a partir de birthdate
 * y con índice: buscar a los cumpleañeros del día deja de revisar a todos los
 * clientes uno por uno. Columna generada: no se escribe desde la app y no
 * cambia ningún dato existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('birth_md', 5)->nullable()->storedAs("DATE_FORMAT(birthdate, '%m-%d')")->after('birthdate');
            $table->index('birth_md');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['birth_md']);
            $table->dropColumn('birth_md');
        });
    }
};
