<?php

/**
 * Écran d'édition de la matrice des droits d'un établissement.
 *
 * Le gabarit vit dans le code de l'application et suit ses routes : la console
 * ne le connaît pas, elle le demande. Elle ne renvoie ensuite que les écarts.
 */

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['provisioning.reporting_secret' => 'jeton-de-service']);
});

function matriceFeinte(array $ecarts = []): array
{
    return [
        'catalogue' => [
            'economat.items.voir'  => ['econome', 'manager'],
            'economat.items.creer' => ['econome', 'manager'],
        ],
        'modules' => ['economat'],
        'roles'   => [
            ['slug' => 'econome',    'name' => 'Économe',  'module' => 'economat',    'is_assignable' => true],
            ['slug' => 'accountant', 'name' => 'Comptable','module' => 'comptabilite','is_assignable' => true],
        ],
        'ecarts'           => $ecarts,
        'incompatibilites' => [['roles' => ['econome', 'accountant'], 'motif' => 'Détenir le stock et tenir les livres.']],
    ];
}

function etablissementProvisionne(): Tenant
{
    return etablissementValide([
        'slug'                 => 'zingana',
        'docker_app_container' => 'meka-erp-zingana-app',
        'provisioned_at'       => now(),
    ]);
}

test("l'écran rend la matrice lue depuis l'établissement", function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceFeinte(), 200)]);

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk()
        ->assertSee('economat.items.creer')
        ->assertSee('econome')
        // Les cumuls refusés sont rappelés à l'écran.
        ->assertSee('Détenir le stock et tenir les livres.');
});

test("un établissement injoignable ne fait cocher aucune case", function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refusée'));

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk()
        ->assertSee('Matrice indisponible')
        ->assertSee('injoignable')
        ->assertDontSee('economat.items.creer');
});

test("un établissement non provisionné le dit clairement", function () {
    Http::fake();

    $tenant = etablissementValide(['slug' => 'neuf', 'provisioned_at' => null]);

    $this->actingAs(User::find($tenant->owner_id))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertSee("n'est pas encore provisionné");

    Http::assertNothingSent();
});

test('les écarts sont transmis à la bonne adresse, avec le jeton', function () {
    Http::fake(['*' => Http::response(['appliques' => 1], 200)]);

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'ecarts' => [
                ['role' => 'accountant', 'permission' => 'economat.items.creer', 'effect' => 'deny', 'reason' => 'Séparation des tâches.'],
            ],
        ])
        ->assertRedirect();

    Http::assertSent(function ($requete) {
        // Le conteneur sur le réseau Docker : 127.0.0.1 désignerait l'ERP.
        return $requete->url() === 'http://meka-erp-zingana-app/api/permissions/matrice'
            && $requete->method() === 'PUT'
            && $requete->hasHeader('Authorization', 'Bearer jeton-de-service')
            && $requete['ecarts'][0]['effect'] === 'deny';
    });
});

test("un refus de l'établissement est rapporté, pas tu", function () {
    Http::fake(['*' => Http::response(['inconnus' => ['economat.licornes.creer']], 422)]);

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'ecarts' => [['role' => 'econome', 'permission' => 'economat.licornes.creer', 'effect' => 'deny']],
        ])
        ->assertSessionHas('error');
});

test('la modification est consignée au journal', function () {
    Http::fake(['*' => Http::response(['appliques' => 1], 200)]);

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'ecarts' => [['role' => 'accountant', 'permission' => 'economat.items.creer', 'effect' => 'deny']],
        ]);

    // Une matrice de droits qui change sans trace ne se contrôle pas.
    $this->assertDatabaseHas('audit_logs', ['event_type' => 'permission_matrix', 'module' => 'security']);
});

test("un propriétaire étranger n'ouvre pas la matrice", function () {
    Http::fake();

    $tenant = etablissementProvisionne();

    $this->actingAs(User::factory()->create(['role' => 'owner']))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertForbidden();
});

test("l'écran propose une étendue sur les droits de consultation", function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(array_merge(matriceFeinte(), [
        'portees' => [
            ['valeur' => 'propre',        'libelle' => 'Ses propres données'],
            ['valeur' => 'departement',   'libelle' => 'Son département'],
            ['valeur' => 'etablissement', 'libelle' => "Tout l'établissement"],
        ],
    ]), 200)]);

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk()
        ->assertSee('Son département')
        // L'étendue ne porte que sur la consultation : restreindre une écriture
        // n'aurait pas de sens, c'est le droit lui-même qu'on retire.
        ->assertSee('data-portee-de="econome|economat.items.voir"', false)
        ->assertDontSee('data-portee-de="econome|economat.items.creer"', false);
});

test("une version d'application sans portées n'en invente pas", function () {
    // matriceFeinte() ne déclare aucune portée : l'écran ne doit proposer que
    // l'établissement, et surtout pas des valeurs que l'application ignore.
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceFeinte(), 200)]);

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk()
        ->assertSee("Tout l'établissement")
        ->assertDontSee('Son département');
});

test("la portée est transmise avec l'écart", function () {
    Http::fake(['*' => Http::response(['appliques' => 1], 200)]);

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'ecarts' => [['role' => 'controller', 'permission' => 'users.voir', 'effect' => 'allow', 'scope' => 'departement']],
        ])->assertRedirect();

    Http::assertSent(fn ($r) => $r['ecarts'][0]['scope'] === 'departement');
});

test('une portée inconnue est refusée avant même de partir', function () {
    Http::fake();

    $tenant = etablissementProvisionne();

    $this->actingAs(User::find($tenant->owner_id))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'ecarts' => [['role' => 'controller', 'permission' => 'users.voir', 'effect' => 'allow', 'scope' => 'planete']],
        ])->assertSessionHasErrors('ecarts.0.scope');

    Http::assertNothingSent();
});
