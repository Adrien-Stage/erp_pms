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
    // Le slug reste figé : il nomme le conteneur et la base de
    // l'établissement, et le renommer les laisserait orphelins.
    expect($tenant->currency)->toBe('USD');
    expect($tenant->settings['country'])->toBe('Cameroun');
    expect($tenant->settings['theme']['primary'])->toBe('#1E3A8A');
    expect($tenant->settings['theme']['secondary'])->toBe('#3B82F6');
    expect($tenant->settings['logo'])->not->toBeEmpty();
    Storage::disk('public')->assertExists($tenant->settings['logo']);


    // La modification d'un établissement laisse une trace : c'est le métier
    // de cette console.
    $log = AuditLog::where('event_type', 'update_tenant')->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('Modification des informations générales');
    expect($log->user_id)->toBe($admin->id);
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

