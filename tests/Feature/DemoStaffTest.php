<?php

/**
 * Personnel de démonstration : un mot de passe propre à chaque établissement,
 * chiffré et stable, et la liste des comptes avec leurs accès sur la fiche
 * de l'établissement, pour l'administrateur TECH.
 */

use App\Models\Tenant;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

test("le mot de passe de démonstration est tiré une fois, gardé chiffré, jamais sérialisé", function () {
    $tenant = demoTenant();
    $service = app(DemoDataService::class);

    $premier = $service->motDePasse($tenant);

    expect($premier)->toHaveLength(12)
        ->and($service->motDePasse($tenant->fresh()))->toBe($premier)
        ->and(DB::table('tenants')->where('id', $tenant->id)->value('demo_password'))->not->toContain($premier)
        ->and($tenant->fresh()->toArray())->not->toHaveKey('demo_password');

    expect($service->motDePasse(demoTenant()))->not->toBe($premier);
});

test("la fiche de l'établissement liste le personnel de démonstration et ses accès", function () {
    $tenant = demoTenant();
    $tenant->forceFill(['demo_password' => 'MotDePasseDemo'])->save();

    $this->mock(DemoDataService::class, function ($mock) {
        $mock->shouldReceive('estInstalle')->andReturn(true);
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
        ->assertSee('MotDePasseDemo');
});
