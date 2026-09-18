<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@garage.com'], // Évite les doublons si on relance le seeder
            [
                'name' => 'Administrateur',
                'email' => 'admin@garage.com',
                'password' => Hash::make('password123'), // Changez ce mot de passe par la suite
                // 'role' => 'admin', // Décommentez ou adaptez selon la gestion de vos rôles
            ]
        );
    }
}