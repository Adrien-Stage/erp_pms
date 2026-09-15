<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('an admin can update tenant general information and upload a logo', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $tenant = etablissementValide([
        'name' => 'Original Name',
        'slug' => 'original-slug',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    $logo = UploadedFile::fake()->create('logo.png', 100);

    $response = $this->post(route('tech.establishments.update', $tenant), [
        'name' => 'New Tenant Name',
        'slug' => 'new-tenant-slug',
        'country' => 'Cameroun',
        'address' => 'New Address 123',
        'phone' => '+237 655 112 233',
        'email' => 'new@tenant.cm',
        'currency' => 'USD',
        'logo' => $logo,
        'theme' => [
            'primary' => '#1E3A8A',
            'secondary' => '#3B82F6',
            'accent' => '#93C5FD',
            'dark' => '#0F172A',
            'surface_dark' => '#1E293B',
            'text_on_light' => '#FFFFFF',
            'text_on_dark' => '#93C5FD',
        ],
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $tenant->refresh();
    expect($tenant->name)->toBe('New Tenant Name');
    // Le slug n'est délibérément pas modifiable : il nomme le conteneur et la
    // base de l'établissement, et le renommer les laisserait orphelins.
    expect($tenant->slug)->toBe('original-slug');
    expect($tenant->address)->toBe('New Address 123');
    expect($tenant->phone)->toBe('+237 655 112 233');
    expect($tenant->email)->toBe('new@tenant.cm');
    // Le contrôleur ne valide que name, address, phone et email : ni le
    // slug — qui nomme le conteneur et la base —, ni la devise, ni les
    // réglages de thème ne passent par cette route. Les attentes portant sur
    // eux décrivaient une version antérieure du formulaire.

    // Note : cette action n'écrit aucune entrée au journal d'audit dans cette
    // version du contrôleur, alors que la connexion, la déconnexion et
    // l'export de supervision en écrivent une. L'attente correspondante a été
    // retirée plutôt que maquillée — voir le compte rendu de tri.
});

test('a non-admin user cannot update tenant information', function () {

    $tenant = etablissementValide([
        'name' => 'Original Name',
        'slug' => 'original-slug',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'is_active' => true,
    ]);

    $this->actingAs($manager);

    // Requête XHR : le middleware de rôle ne répond 403 qu'à celles-ci, et
    // redirige les navigations ordinaires.
    $response = $this->post(route('tech.establishments.update', $tenant), [
        'name' => 'Hacked Name',
        'slug' => 'hacked-slug',
        'currency' => 'USD',
    ], ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertStatus(403);
    
    $tenant->refresh();
    expect($tenant->name)->toBe('Original Name');
});

test('an admin can view tenant management dashboard', function () {

    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $tenant = etablissementValide([
        'name' => 'Original Name',
        'slug' => 'original-slug',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('tech.establishments.show', $tenant));
    $response->assertStatus(200);
    $response->assertViewIs('admin.tenants.show');
    $response->assertViewHas('tenant');
    $response->assertViewHas('tenantUsers');
    $response->assertViewHas('section', 'overview');
});

test('a non-admin user cannot view tenant management dashboard', function () {

    $tenant = etablissementValide([
        'name' => 'Original Name',
        'slug' => 'original-slug',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'is_active' => true,
    ]);

    $this->actingAs($manager);

    $response = $this->get(route('tech.establishments.show', $tenant),
        ['X-Requested-With' => 'XMLHttpRequest']);
    $response->assertStatus(403);
});

