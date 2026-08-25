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
        $admin = User::firstOrCreate(
            ['email' => 'admin'],
            [
                'name' => 'Administrateur Technique',
                'password' => Hash::make('admin'),
                'role' => User::ROLE_TECH_ADMIN,
                'is_active' => true,
            ]
        );

        // Créer un propriétaire d'établissement par défaut
        $owner = User::firstOrCreate(
            ['email' => 'owner'],
            [
                'name' => 'Propriétaire Business',
                'password' => Hash::make('owner'),
                'role' => User::ROLE_OWNER,
                'is_active' => true,
            ]
        );

        // Créer un établissement de démonstration lié au propriétaire
        \App\Models\Tenant::firstOrCreate(
            ['slug' => 'demo-resort'],
            [
                'name' => 'Résidence Démo',
                'owner_id' => $owner->id,
                'db_name' => 'demo_resort_db',
                'is_active' => true,
                'currency' => 'XAF',
                'settings' => [
                    'country' => 'Cameroun',
                    'city' => 'Douala',
                ]
            ]
        );
    }
}
