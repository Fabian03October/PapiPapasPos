<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'cajero']);
        Role::firstOrCreate(['name' => 'manager']);
        // Vende y entra al panel admin (todo menos "Administración"); cancela
        // sin código y genera códigos para los cajeros.
        Role::firstOrCreate(['name' => 'gerente']);
    }
}