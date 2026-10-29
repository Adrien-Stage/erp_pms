<?php

/**
 * Personnel de démonstration : un mot de passe commun, celui du jeu, et la
 * liste des comptes avec leurs accès sur la fiche de l'établissement, pour
 * l'administrateur TECH.
 */

use App\Models\Tenant;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function demoTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Hôtel Démo',
        'slug' => 'hotel-demo-' . random_int(1000, 99999),
        'db_name' => 'db_demo_' . random_int(1000, 99999),
        'owner_id' => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'docker_app_container' => 'meka-erp-hotel-demo-app',
        'docker_status' => 'running',
        'provisioned_at' => now(),
        'is_active' => true,
    ]);
}

test("tous les comptes de démonstration partagent le mot de passe du jeu", function () {
    $jeu = json_decode(file_get_contents(base_path('demo/data/staff.json')), true);

    expect(app(DemoDataService::class)->motDePasse())->toBe('Test@2026')->toBe($jeu['password']);

    // Chaque équipe a au moins deux membres, tous sur le domaine réservé.
    $equipes = array_count_values(array_column($jeu['staff'], 'team'));
    expect(min($equipes))->toBeGreaterThanOrEqual(2);
    foreach ($jeu['staff'] as $personne) {
        expect($personne['email'])->toEndWith('@' . \Demo\Seeders\StaffSeeder::DOMAINE)
            ->and($personne['role'])->not->toBeIn(['admin', 'support', 'customer_guest']);
    }
});

test("la fiche de l'établissement liste le personnel de démonstration et ses accès", function () {
    $tenant = demoTenant();

    $this->mock(DemoDataService::class, function ($mock) {
        $mock->shouldReceive('estInstalle')->andReturn(true);
        $mock->shouldReceive('motDePasse')->andReturn('Test@2026');
        $mock->shouldReceive('comptes')->andReturn([[
            'name' => 'Gaston Ewane', 'email' => 'gaston.ewane@demo.wetchah.test', 'team' => 'Achats',
            'title' => null, 'role' => 'purchasing_manager', 'role_name' => 'Responsable des achats',
            'acces' => 'Achats : fournisseurs, bons de commande', 'department' => null, 'actif' => true,
        ]]);
    });

    $tech = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($tech)->get(route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'settings']))
        ->assertOk()
        ->assertSee('Personnel de démonstration — 1 comptes')
        ->assertSee('gaston.ewane@demo.wetchah.test')
        ->assertSee('Responsable des achats')
        ->assertSee('Achats : fournisseurs, bons de commande')
        ->assertSee('Test@2026');
});
