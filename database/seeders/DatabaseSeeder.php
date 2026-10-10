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

        // Entorno staging de Railway: catálogo, cajero y caja abierta para
        // probar. Solo con SEED_STAGING_DATA=true - producción nunca la tiene.
        if (filter_var(env('SEED_STAGING_DATA', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(StagingSeeder::class);
        }
    }
}