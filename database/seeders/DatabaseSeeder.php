<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        // Catálogo de ejemplo (categorías/productos falsos) solo para
        // desarrollo local - nunca en producción.
        if (! app()->environment('production')) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}