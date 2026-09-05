<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cajeroRole = Role::where('name', 'cajero')->first();
        $managerRole = Role::where('name', 'manager')->first();

        User::firstOrCreate(
            ['email' => 'cajero@pospapis.test'],
            [
                'name' => 'Cajero de prueba',
                'password' => 'password',
                'pin' => '1234',
                'role_id' => $cajeroRole->id,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@pospapis.test'],
            [
                'name' => 'Manager de prueba',
                'password' => 'password',
                'pin' => '9999',
                'role_id' => $managerRole->id,
                'is_active' => true,
            ]
        );
    }
}