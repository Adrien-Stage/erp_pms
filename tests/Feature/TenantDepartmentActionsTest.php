<?php

/**
 * Départements d'un établissement, tenus depuis l'ERP par l'API de
 * l'application — plus aucune écriture directe dans sa base.
 */

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['provisioning.reporting_secret' => 'jeton-de-service']);
});

function departmentTestTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Hôtel Test Département',
        'slug' => 'hotel-test-' . random_int(1000, 99999),
        'db_name' => 'db_test_' . random_int(1000, 99999),
        'owner_id' => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'docker_app_container' => 'meka-erp-hotel-test-app',
        'provisioned_at' => now(),
        'is_active' => true,
        'users_count' => 5,
    ]);
}

function adminTechnique(): User
{
    return User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
}

test('créer un département passe par l\'API, avec ses modules', function () {
    Http::fake(['*' => Http::response(['id' => 12], 201)]);
    $tenant = departmentTestTenant();

    $this->actingAs(adminTechnique())
        ->post(route('tech.establishments.departments.store', $tenant), [
            'name' => 'Réception & Accueil',
            'code' => 'REC',
            'icon' => 'calendar-check',
            'modules' => ['reservations', 'hebergement'],
            'levels' => ['reservations' => 'write', 'hebergement' => 'read'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r->url() === 'http://meka-erp-hotel-test-app/api/departements'
        && $r->hasHeader('Authorization', 'Bearer jeton-de-service')
        && $r['name'] === 'Réception & Accueil'
        && $r['modules'] === ['reservations' => 'write', 'hebergement' => 'read']);
});

test('modifier un département passe par l\'API', function () {
    Http::fake(['*' => Http::response(['id' => 3], 200)]);
    $tenant = departmentTestTenant();

    $this->actingAs(adminTechnique())
        ->put(route('tech.establishments.departments.update', ['tenant' => $tenant, 'department' => 3]), [
            'name' => 'Hébergement & Housekeeping',
            'modules' => ['housekeeping'],
        ])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PUT'
        && str_ends_with($r->url(), '/api/departements/3')
        && $r['name'] === 'Hébergement & Housekeeping');
});

test('supprimer un département passe par l\'API', function () {
    Http::fake(['*' => Http::response(['supprime' => 5], 200)]);
    $tenant = departmentTestTenant();

    $this->actingAs(adminTechnique())
        ->delete(route('tech.establishments.departments.destroy', ['tenant' => $tenant, 'department' => 5]))
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/departements/5'));
});

test("un établissement pas encore à jour le dit, sans rien écrire", function () {
    Http::fake(['*' => Http::response([], 404)]);
    $tenant = departmentTestTenant();

    $this->actingAs(adminTechnique())
        ->post(route('tech.establishments.departments.store', $tenant), ['name' => 'Cuisine'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'mettez-le à jour'));
});

test('un refus de validation est rapporté tel quel', function () {
    Http::fake(['*' => Http::response([
        'message' => 'Données invalides.',
        'errors' => ['slug' => ['Un département porte déjà cet identifiant.']],
    ], 422)]);
    $tenant = departmentTestTenant();

    $this->actingAs(adminTechnique())
        ->post(route('tech.establishments.departments.store', $tenant), ['name' => 'Cuisine', 'slug' => 'cuisine'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Un département porte déjà cet identifiant.'));
});

test('un établissement injoignable donne un message clair', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refusée'));
    $tenant = departmentTestTenant();

    $this->actingAs(adminTechnique())
        ->delete(route('tech.establishments.departments.destroy', ['tenant' => $tenant, 'department' => 5]))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'injoignable'));
});
