<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Appel de votre seeder admin
        $this->call([
            AdminUserSeeder::class,
        ]);
    }
}