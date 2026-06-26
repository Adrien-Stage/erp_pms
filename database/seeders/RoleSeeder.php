<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            // Rôles globaux (tenant_id = null)
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrateur global - accès complet à tous les hôtels',
                'tenant_id' => null,
            ],

            // Rôles par hôtel (tenant-specific)
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Directeur d\'hôtel - gestion complète de l\'établissement',
                'tenant_id' => null, // Sera créé pour chaque tenant
            ],
            [
                'name' => 'Réceptionniste',
                'slug' => 'reception',
                'description' => 'Accueil et gestion des réservations',
                'tenant_id' => null,
            ],
            [
                'name' => 'Chef d\'équipe Housekeeping',
                'slug' => 'housekeeping_leader',
                'description' => 'Superviseur du service ménage',
                'tenant_id' => null,
            ],
            [
                'name' => 'Équipe Housekeeping',
                'slug' => 'housekeeping_staff',
                'description' => 'Personnel de ménage',
                'tenant_id' => null,
            ],
            [
                'name' => 'Chef cuisinier',
                'slug' => 'restaurant_chief',
                'description' => 'Responsable de la cuisine et restaurant',
                'tenant_id' => null,
            ],
            [
                'name' => 'Serveur/Cuisinier',
                'slug' => 'restaurant_staff',
                'description' => 'Personnel de restaurant',
                'tenant_id' => null,
            ],
            [
                'name' => 'Caissier',
                'slug' => 'cashier',
                'description' => 'Gestion des encaissements et facturation',
                'tenant_id' => null,
            ],
            [
                'name' => 'Comptable',
                'slug' => 'accountant',
                'description' => 'Service comptabilité et rapports financiers',
                'tenant_id' => null,
            ],
            [
                'name' => 'Client',
                'slug' => 'customer_guest',
                'description' => 'Accès client au portail client',
                'tenant_id' => null,
            ],
            [
                'name' => 'Gérant Boutique',
                'slug' => 'shop_manager',
                'description' => 'Gestion des articles culturels et stocks boutique',
                'tenant_id' => null,
            ],
            [
                'name' => 'Caissier Boutique',
                'slug' => 'shop_cashier',
                'description' => 'Ventes et encaissements boutique',
                'tenant_id' => null,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }
    }
}
