<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a local database (`php artisan migrate:fresh --seed`) without production data.
     */
    public function run(): void
    {
        $this->call(LocalAdminSeeder::class);
    }
}
