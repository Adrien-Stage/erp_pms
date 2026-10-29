<?php

/**
 * Corbeille d'un établissement, vue depuis la console : réservée à
 * l'administrateur TECH, filtrable ; la restauration et la purge passent
 * par l'API de l'application, qui seule sait remettre ses données en place.
 */

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['provisioning.reporting_secret' => 'jeton-de-service']);
});

function corbeilleTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Hôtel Corbeille',
        'slug' => 'hotel-corbeille-' . random_int(1000, 99999),
        'db_name' => 'db_corbeille_' . random_int(1000, 99999),
        'owner_id' => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'docker_app_container' => 'meka-erp-hotel-corbeille-app',
        'provisioned_at' => now(),
        'is_active' => true,
    ]);
}

function techCorbeille(): User
{
    return User::where('email', 'tech@wetchah.test')->first()
        ?? User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true, 'name' => 'Tech Wetchah', 'email' => 'tech@wetchah.test']);
}

function pageDeCorbeille(array $entrees = []): array
{
    return [
        'entrees' => $entrees,
        'pagination' => ['page' => 1, 'pages' => 1, 'total' => count($entrees), 'par_page' => 50],
        'filtres' => [
            'types' => ['suppliers' => 'Fournisseur', 'goods_receipt_attachments' => 'Bordereau de livraison'],
            'modules' => ['economat' => 'Économat'],
            'auteurs' => ['Marie Ngono'],
            'etats' => ['dans' => 'Dans la corbeille', 'restauree' => 'Restaurées', 'tout' => 'Toutes'],
        ],
    ];
}

function entreeDeCorbeille(array $plus = []): array
{
    return $plus + [
        'id' => 7, 'type' => 'Fournisseur', 'table' => 'suppliers', 'module' => 'economat', 'module_nom' => 'Économat',
        'libelle' => 'Grossiste Nord', 'supprime_par' => 'Marie Ngono', 'supprime_le' => '2026-10-29T08:00:00+00:00',
        'restaure_le' => null, 'restaure_par' => null,
        'contenu' => ['lignes_emportees' => 0, 'liens' => 3, 'fichiers' => 0],
    ];
}

test("l'administrateur TECH voit la corbeille, filtrée par l'établissement", function () {
    Http::fake(['*/api/corbeille*' => Http::response(pageDeCorbeille([entreeDeCorbeille()]))]);
    $tenant = corbeilleTenant();

    $this->actingAs(techCorbeille())
        ->get(route('tech.establishments.corbeille', ['tenant' => $tenant, 'type' => 'suppliers', 'q' => 'nord']))
        ->assertOk()
        ->assertSee('Grossiste Nord')->assertSee('Marie Ngono')->assertSee('3 lien(s) à rétablir')
        ->assertSee('Bordereau de livraison')->assertSee('Restaurer')->assertSee('Purger');

    Http::assertSent(fn ($r) => $r->method() === 'GET'
        && str_starts_with($r->url(), 'http://meka-erp-hotel-corbeille-app/api/corbeille')
        && $r['type'] === 'suppliers' && $r['q'] === 'nord' && $r['etat'] === 'dans'
        && $r->hasHeader('Authorization', 'Bearer jeton-de-service'));

    // La fiche de l'établissement mène à la corbeille.
    $this->actingAs(techCorbeille())->get(route('tech.establishments.show', $tenant))
        ->assertOk()->assertSee(route('tech.establishments.corbeille', $tenant), false);
});

test("restaurer et purger passent par l'API, au nom de l'administrateur, et sont tracés", function () {
    Http::fake([
        '*/api/corbeille/7/restaurer' => Http::response(['entree' => entreeDeCorbeille(['restaure_le' => now()->toIso8601String()])]),
        '*/api/corbeille/8' => Http::response(['purgee' => 8]),
    ]);
    $tenant = corbeilleTenant();
    $tech = techCorbeille();

    $this->actingAs($tech)->post(route('tech.establishments.corbeille.restaurer', ['tenant' => $tenant, 'entree' => 7]), [
        'libelle' => 'Grossiste Nord', 'filtres' => ['type' => 'suppliers'],
    ])->assertRedirect(route('tech.establishments.corbeille', ['tenant' => $tenant, 'type' => 'suppliers']))
        ->assertSessionHas('success');

    $this->actingAs($tech)->delete(route('tech.establishments.corbeille.purger', ['tenant' => $tenant, 'entree' => 8]), [
        'libelle' => 'BL-1.pdf',
    ])->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/corbeille/7/restaurer')
        && $r['auteur'] === 'Tech Wetchah <tech@wetchah.test>');
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/corbeille/8')
        && $r['auteur'] === 'Tech Wetchah <tech@wetchah.test>');

    expect(AuditLog::where('event_type', 'tenant_corbeille_restore')->exists())->toBeTrue()
        ->and(AuditLog::where('event_type', 'tenant_corbeille_purge')->exists())->toBeTrue();
});

test("une restauration refusée par l'établissement affiche son motif", function () {
    Http::fake(['*/api/corbeille/7/restaurer' => Http::response(['message' => "Il dépend d'un élément qui n'existe plus (sa catégorie…) : restaurez d'abord celui-ci depuis la corbeille."], 422)]);
    $tenant = corbeilleTenant();

    $this->actingAs(techCorbeille())->post(route('tech.establishments.corbeille.restaurer', ['tenant' => $tenant, 'entree' => 7]), ['libelle' => 'Sel'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'restaurez d\'abord'));
});

test("un établissement sans corbeille (version antérieure) le dit, sans planter", function () {
    Http::fake(['*' => Http::response(['message' => 'Not Found'], 404)]);

    $this->actingAs(techCorbeille())->get(route('tech.establishments.corbeille', corbeilleTenant()))
        ->assertOk()->assertSee('Corbeille indisponible')->assertSee("n'a pas encore la corbeille");
});

test("le propriétaire n'accède pas à la corbeille", function () {
    Http::fake();
    $tenant = corbeilleTenant();

    // L'espace TECH le renvoie ailleurs : il n'y voit rien et n'y fait rien.
    $this->actingAs($tenant->owner)->get('/tech/establishments/' . $tenant->id . '/corbeille')->assertRedirect();
    $this->actingAs($tenant->owner)->post('/tech/establishments/' . $tenant->id . '/corbeille/7/restaurer')->assertRedirect();

    Http::assertNothingSent();
});
