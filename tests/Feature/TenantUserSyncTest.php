<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDatabase;
use App\Services\TenantDirectoryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

class SyncTestDatabaseDouble extends TenantDatabase
{
    public int $simulatedCount = 0;
    public int $countCalls = 0;

    public function countUsers(Tenant $tenant): int
    {
        $this->countCalls++;
        return $this->simulatedCount;
    }

    public function getRunningContainers(): ?array
    {
        // Simuler que le conteneur du tenant est actif
        return ['meka-erp-sync-test-db'];
    }
}

test('syncUsersCount met a jour users_count sur le tenant', function () {
    $tenant = etablissementValide([
        'slug' => 'sync-test',
        'users_count' => 0,
        'provisioned_at' => now(),
        'docker_db_container' => 'meka-erp-sync-test-db',
    ]);

    $double = new SyncTestDatabaseDouble();
    $double->simulatedCount = 2;
    $this->app->instance(TenantDatabase::class, $double);

    expect($tenant->users_count)->toBe(0);

    $count = $double->syncUsersCount($tenant);

    expect($count)->toBe(2);
    expect($tenant->fresh()->users_count)->toBe(2);
});

test('syncUsersCountForTenants synchronise les etablissements actifs et provisionnes', function () {
    $tenant1 = etablissementValide([
        'slug' => 'sync-test',
        'users_count' => 0,
        'provisioned_at' => now(),
        'docker_db_container' => 'meka-erp-sync-test-db',
    ]);

    $double = new SyncTestDatabaseDouble();
    $double->simulatedCount = 5;
    $this->app->instance(TenantDatabase::class, $double);

    $double->syncUsersCountForTenants([$tenant1]);

    expect($tenant1->fresh()->users_count)->toBe(5);
});

test('le dashboard synchronise users_count pour les etablissements affiches', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $tenant = etablissementValide([
        'name' => 'Palmier Ebene',
        'slug' => 'sync-test',
        'users_count' => 0,
        'provisioned_at' => now(),
        'docker_db_container' => 'meka-erp-sync-test-db',
    ]);

    $double = new SyncTestDatabaseDouble();
    $double->simulatedCount = 2;
    $this->app->instance(TenantDatabase::class, $double);

    $this->actingAs($admin)
        ->get(route('tech.dashboard', ['tab' => 'establishments']))
        ->assertOk()
        ->assertSee('2'); // Le compteur 2 doit être affiché sur la carte

    expect($tenant->fresh()->users_count)->toBe(2);
});

test('la fiche etablissement synchronise users_count meme sur overview', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $tenant = etablissementValide([
        'slug' => 'sync-test',
        'users_count' => 0,
        'provisioned_at' => now(),
        'docker_db_container' => 'meka-erp-sync-test-db',
    ]);

    $double = new SyncTestDatabaseDouble();
    $double->simulatedCount = 2;
    $this->app->instance(TenantDatabase::class, $double);

    $this->actingAs($admin)
        ->get(route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'overview']))
        ->assertOk();

    expect($tenant->fresh()->users_count)->toBe(2);
});

test('la creation dun administrateur synchronise immediatement users_count', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $tenant = etablissementValide([
        'slug' => 'sync-test',
        'users_count' => 0,
        'provisioned_at' => now(),
        'docker_db_container' => 'meka-erp-sync-test-db',
    ]);

    $double = new SyncTestDatabaseDouble();
    $double->simulatedCount = 1;
    $this->app->instance(TenantDatabase::class, $double);

    // Mock de l'API de création d'administrateur
    $annuaire = Mockery::mock(TenantDirectoryClient::class);
    $annuaire->shouldReceive('creerAdministrateur')
        ->once()
        ->andReturn(['ok' => true, 'message' => '', 'id' => 1]);
    $this->app->instance(TenantDirectoryClient::class, $annuaire);

    $this->actingAs($admin)
        ->post(route('tech.establishments.admins.store', $tenant), [
            'name' => 'Fanche Clyde',
            'email' => 'admin@palmier.cm',
            'password' => 'password123',
        ])
        ->assertRedirect();

    expect($tenant->fresh()->users_count)->toBe(1);
});
