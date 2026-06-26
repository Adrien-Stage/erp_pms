<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Créer l'administrateur technique par défaut
        User::firstOrCreate(
            ['email' => 'admin'],
            [
                'name' => 'Administrateur Technique',
                'password' => Hash::make('admin'),
                'role' => User::ROLE_TECH_ADMIN,
                'is_active' => true,
            ]
        );
    }
}
