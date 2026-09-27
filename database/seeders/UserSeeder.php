<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Crea una sola cuenta de manager "de arranque" si todavía no hay
     * ninguna. El resto del personal (más managers, cajeros) se da de alta
     * después, con sus datos reales, desde el panel de Filament.
     *
     * El PIN inicial es de un solo uso: must_change_pin obliga a elegir uno
     * propio en el primer login, así que nunca hace falta poner un PIN real
     * aquí ni en las variables de entorno.
     */
    public function run(): void
    {
        if (User::whereHas('role', fn ($q) => $q->where('name', 'manager'))->exists()) {
            return;
        }

        $managerRole = Role::where('name', 'manager')->first();

        User::create([
            'name' => env('INITIAL_MANAGER_NAME', 'Manager'),
            'email' => env('INITIAL_MANAGER_EMAIL', 'manager@pospapis.test'),
            'password' => str()->random(32),
            'pin' => env('INITIAL_MANAGER_PIN', '0000'),
            'role_id' => $managerRole->id,
            'is_active' => true,
            'must_change_pin' => true,
        ]);
    }
}
