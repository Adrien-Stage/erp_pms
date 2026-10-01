<?php

/**
 * Droits & rôles v2 : la matrice en couches, l'aperçu avant envoi,
 * l'historique des versions et les comptes administrateurs.
 *
 * La console ne règle que sa propre couche ; l'établissement calcule tout ce
 * qui dépend de ses comptes et de son code (aperçu, cumuls, revue).
 */

use App\Models\PermissionMatrixVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['provisioning.reporting_secret' => 'jeton-de-service']);
});

function matriceV2(array $ecarts = [], array $versionComptes = []): array
{
    return [
        'version'   => 2,
        'catalogue' => [
            'economat.items.voir'  => ['admin', 'econome', 'manager', 'storekeeper'],
            'economat.items.creer' => ['econome'],
            'users.voir'           => ['admin', 'manager'],
            'accounting.cash_reviews.creer' => ['accountant'],
        ],
        'modules'   => ['accounting', 'economat', 'users'],
        'ecritures' => ['economat.items.creer', 'accounting.cash_reviews.creer'],
        'roles'     => [
            ['slug' => 'admin', 'name' => 'Administrateur', 'description' => 'Service informatique', 'module' => 'it', 'is_assignable' => false, 'level' => 1, 'includes' => [], 'statut' => 'actif', 'reglable' => false, 'titulaires' => 1],
            ['slug' => 'manager', 'name' => 'Manager', 'description' => 'Direction', 'module' => 'direction', 'is_assignable' => false, 'level' => 2, 'includes' => [], 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
            ['slug' => 'reception', 'name' => 'Réceptionniste', 'description' => 'Accueil', 'module' => 'hebergement', 'is_assignable' => true, 'level' => 4, 'includes' => [], 'statut' => 'actif', 'reglable' => true, 'titulaires' => 2],
            ['slug' => 'econome', 'name' => 'Chef économe', 'description' => 'Magasin', 'module' => 'economat', 'is_assignable' => true, 'level' => 3, 'includes' => ['storekeeper'], 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
            ['slug' => 'storekeeper', 'name' => 'Magasinier', 'description' => 'Magasin', 'module' => 'economat', 'is_assignable' => true, 'level' => 4, 'includes' => [], 'statut' => 'actif', 'reglable' => true, 'titulaires' => 0],
            ['slug' => 'cashier', 'name' => 'Caissier restaurant', 'description' => 'Caisse', 'module' => 'restaurant', 'is_assignable' => true, 'level' => 4, 'includes' => [], 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
            ['slug' => 'accountant', 'name' => 'Comptable', 'description' => 'Livres', 'module' => 'comptabilite', 'is_assignable' => true, 'level' => 4, 'includes' => [], 'statut' => 'actif', 'reglable' => true, 'titulaires' => 0],
            ['slug' => 'controller', 'name' => 'Contrôleur de gestion', 'description' => 'Contrôle', 'module' => 'comptabilite', 'is_assignable' => true, 'level' => null, 'includes' => [], 'statut' => 'actif', 'reglable' => true, 'titulaires' => 0],
            ['slug' => 'it_support', 'name' => 'Technicien IT', 'description' => 'Retiré', 'module' => 'it', 'is_assignable' => false, 'level' => null, 'includes' => [], 'statut' => 'retire', 'reglable' => false, 'titulaires' => 0],
        ],
        'ecarts'    => $ecarts,
        'exceptions_echues' => [],
        'restrictions' => [['user_id' => 7, 'service' => 'economat', 'niveau' => 'read']],
        'comptes'   => $versionComptes ?: [
            ['id' => 1, 'name' => 'Paul Essomba', 'email' => 'paul@hotel.test', 'phone' => null, 'roles' => ['admin'], 'lecture_seule' => [], 'actif' => true, 'departement' => null, 'derniere_connexion' => null],
            ['id' => 7, 'name' => 'Rose Ngo', 'email' => 'rose@hotel.test', 'phone' => null, 'roles' => ['econome'], 'lecture_seule' => [], 'actif' => true, 'departement' => null, 'derniere_connexion' => null],
        ],
        'constats'  => [['code' => 'aucun_comptable', 'gravite' => 'à corriger', 'constat' => 'Aucun compte en comptabilité.', 'decision' => 'Créer au moins un compte.', 'comptes' => []]],
        'incompatibilites' => [['roles' => ['cashier', 'accountant'], 'motif' => 'Encaisser et enregistrer.']],
        'regles_de_cumul' => [['roles' => ['cashier', 'accountant'], 'motif' => 'Encaisser et enregistrer.']],
        'cumuls'    => ['cashier|accounting.cash_reviews.creer' => [0]],
        'portees'   => [
            ['valeur' => 'propre', 'libelle' => 'Ses propres données'],
            ['valeur' => 'departement', 'libelle' => 'Son département'],
            ['valeur' => 'etablissement', 'libelle' => "Tout l'établissement"],
        ],
        'droits_bornes' => ['users.voir'],
        'empreinte' => 'empreinte-a',
    ];
}

function etablissementV2(): Tenant
{
    return etablissementValide([
        'slug'                 => 'zingana',
        'docker_app_container' => 'meka-erp-zingana-app',
        'provisioned_at'       => now(),
    ]);
}

function proprietaireDe(Tenant $tenant): User
{
    return User::find($tenant->owner_id);
}

// ── Écran ───────────────────────────────────────────────────────────────────

test("un établissement v2 ouvre l'écran en couches, colonnes groupées par service", function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);
    $tenant = etablissementV2();

    $page = $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk()
        ->assertSee('Droits &amp; rôles', false)
        ->assertSee('Hébergement')
        ->assertSee('Économat')
        ->assertSee('Chef économe')
        ->assertSee('N3 · 1 pers.')
        // Le manager se règle ; l'administrateur est montré, figé.
        ->assertSee('data-colonne="manager"', false)
        ->assertSee('data-colonne="admin"', false)
        // Un rôle retiré n'a pas de colonne.
        ->assertDontSee('data-colonne="it_support"', false)
        ->assertSee('id="voir-apercu"', false)
        ->getContent();

    expect($page)->toMatch('/data-role="admin"[^>]*disabled/s');
});

test("les couches de l'hôtel et les exceptions nominatives sont signalées", function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2([
        ['subject_type' => 'role', 'subject_id' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny', 'scope' => null, 'origin' => 'etablissement', 'reason' => 'Décision du directeur', 'expires_at' => null],
        ['subject_type' => 'user', 'subject_id' => '7', 'permission' => 'economat.items.voir', 'effect' => 'allow', 'scope' => null, 'origin' => 'etablissement', 'reason' => 'Inventaire', 'expires_at' => null],
    ]), 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk()
        ->assertSee('refus — Décision du directeur')
        ->assertSee('Rose Ngo : autorisation')
        // Les exceptions sont listées dans leur onglet, avec leur origine.
        ->assertSee('Exceptions nominatives en vigueur')
        ->assertSee('Inventaire')
        // Les restrictions de service héritées aussi.
        ->assertSee('Restrictions de service');
});

test('la portée n\'est proposée que là où un écran borne les données', function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertSee('data-portee-de="manager|users.voir"', false)
        ->assertDontSee('data-portee-de="econome|economat.items.voir"', false);
});

test('les cumuls possibles accompagnent la page, pour alerter au clic', function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertSee('cashier|accounting.cash_reviews.creer', false)
        ->assertSee('Encaisser et enregistrer.');
});

test("un établissement d'avant la v2 garde l'ancien écran", function () {
    $ancienne = matriceV2();
    unset($ancienne['version']);
    Http::fake(['*/api/permissions/matrice' => Http::response($ancienne, 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk()
        ->assertSee('Matrice des droits')
        ->assertDontSee('id="voir-apercu"', false);
});

// ── Aperçu et enregistrement ────────────────────────────────────────────────

test("l'aperçu est calculé par l'établissement", function () {
    Http::fake(['*/api/permissions/matrice/apercu' => Http::response([
        'ecarts' => 1, 'cumuls' => [],
        'personnes' => [['id' => 7, 'name' => 'Rose Ngo', 'roles' => ['econome'], 'gagnes' => [], 'perdus' => ['economat.items.creer'], 'portees' => []]],
    ], 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->postJson(route('business.establishments.permissions.preview', $tenant), [
            'ecarts' => [['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny']],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('apercu.personnes.0.perdus.0', 'economat.items.creer');

    Http::assertSent(fn ($r) => $r->url() === 'http://meka-erp-zingana-app/api/permissions/matrice/apercu' && $r->method() === 'POST');
});

test("le premier enregistrement garde l'état trouvé, puis consigne la version", function () {
    Http::fake([
        '*/api/permissions/matrice' => Http::sequence()
            ->push(matriceV2([
                ['subject_type' => 'role', 'subject_id' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny', 'scope' => null, 'origin' => 'erp', 'reason' => 'Ancien réglage', 'expires_at' => null],
                ['subject_type' => 'role', 'subject_id' => 'reception', 'permission' => 'economat.items.voir', 'effect' => 'allow', 'scope' => null, 'origin' => 'etablissement', 'reason' => 'Hôtel', 'expires_at' => null],
            ]), 200)
            ->push(['appliques' => 1, 'cumuls' => [], 'empreinte' => 'empreinte-b'], 200),
    ]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'empreinte' => 'empreinte-a',
            'motif' => 'Le magasinier crée les articles.',
            'ecarts' => [['role' => 'storekeeper', 'permission' => 'economat.items.creer', 'effect' => 'allow', 'reason' => 'Le magasinier crée les articles.']],
        ])
        ->assertSessionHas('success');

    $versions = PermissionMatrixVersion::where('tenant_id', $tenant->id)->orderBy('numero')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->nature)->toBe('etat_initial')
        // Seule la couche de la console : celle de l'hôtel ne lui appartient pas.
        ->and($versions[0]->ecarts)->toBe([['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny', 'reason' => 'Ancien réglage']])
        ->and($versions[1]->numero)->toBe(1)
        ->and($versions[1]->motif)->toBe('Le magasinier crée les articles.')
        ->and($versions[1]->user_id)->toBe($tenant->owner_id);
});

test("l'empreinte, la dérogation et l'auteur partent avec le lot", function () {
    Http::fake(['*' => Http::response(['appliques' => 1, 'cumuls' => [['role' => 'cashier', 'permission' => 'accounting.cash_reviews.creer', 'roles' => ['cashier', 'accountant'], 'motif' => 'Encaisser et enregistrer.']], 'empreinte' => 'b'], 200)]);
    $tenant = etablissementV2();
    PermissionMatrixVersion::create(['tenant_id' => $tenant->id, 'numero' => 0, 'ecarts' => []]);

    $this->actingAs(proprietaireDe($tenant))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'empreinte' => 'empreinte-a',
            'motif' => 'Pas de comptable le soir.',
            'derogation' => '1',
            'ecarts' => [['role' => 'cashier', 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow', 'reason' => 'Pas de comptable le soir.']],
        ])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PUT'
        && $r['empreinte'] === 'empreinte-a'
        && $r['derogation'] === true
        && str_contains($r['auteur'], '<'));

    expect(PermissionMatrixVersion::where('tenant_id', $tenant->id)->where('numero', 1)->first()->derogation)->toBeTrue();
});

test("un écran périmé ne consigne rien et le dit", function () {
    Http::fake(['*' => Http::response(['message' => 'La matrice a changé.'], 409)]);
    $tenant = etablissementV2();
    PermissionMatrixVersion::create(['tenant_id' => $tenant->id, 'numero' => 0, 'ecarts' => []]);

    $this->actingAs(proprietaireDe($tenant))
        ->put(route('business.establishments.permissions.update', $tenant), [
            'empreinte' => 'empreinte-a', 'motif' => 'Essai', 'ecarts' => [],
        ])
        ->assertSessionHas('error', fn ($m) => str_contains($m, "quelqu'un d'autre"));

    expect(PermissionMatrixVersion::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('la v2 exige un motif', function () {
    Http::fake();
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->put(route('business.establishments.permissions.update', $tenant), ['empreinte' => 'a', 'ecarts' => []])
        ->assertSessionHasErrors('motif');

    Http::assertNothingSent();
});

test("vider la couche de la console est une décision qu'on peut prendre", function () {
    Http::fake(['*' => Http::response(['appliques' => 0, 'cumuls' => [], 'empreinte' => 'c'], 200)]);
    $tenant = etablissementV2();
    PermissionMatrixVersion::create(['tenant_id' => $tenant->id, 'numero' => 0, 'ecarts' => []]);

    // Un formulaire n'envoie pas de tableau vide : aucun champ « ecarts ».
    $this->actingAs(proprietaireDe($tenant))
        ->put(route('business.establishments.permissions.update', $tenant), ['empreinte' => 'a', 'motif' => 'Retour au modèle.'])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['ecarts'] === []);
});

// ── Historique ──────────────────────────────────────────────────────────────

test('revenir à une version renvoie sa couche telle quelle, comme nouvelle version', function () {
    Http::fake(['*' => Http::response(['appliques' => 1, 'cumuls' => [], 'empreinte' => 'd'], 200)]);
    $tenant = etablissementV2();
    $ancienne = PermissionMatrixVersion::create(['tenant_id' => $tenant->id, 'numero' => 0, 'nature' => 'etat_initial',
        'ecarts' => [['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny', 'reason' => 'Ancien']]]);
    PermissionMatrixVersion::create(['tenant_id' => $tenant->id, 'numero' => 1, 'ecarts' => []]);

    $this->actingAs(proprietaireDe($tenant))
        ->post(route('business.establishments.permissions.restore', ['tenant' => $tenant, 'version' => $ancienne]), [
            'motif' => 'Le réglage précédent était le bon.',
        ])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['ecarts'][0]['effect'] === 'deny');

    $retour = PermissionMatrixVersion::where('tenant_id', $tenant->id)->where('numero', 2)->first();
    expect($retour->nature)->toBe('retour')
        ->and($retour->restauree_depuis)->toBe($ancienne->id)
        ->and($retour->motif)->toContain('Le réglage précédent était le bon.');
});

test("la version d'un autre établissement ne se restaure pas", function () {
    Http::fake();
    $tenant = etablissementV2();
    $autre = etablissementValide(['owner_id' => $tenant->owner_id]);
    $version = PermissionMatrixVersion::create(['tenant_id' => $autre->id, 'numero' => 0, 'ecarts' => []]);

    $this->actingAs(proprietaireDe($tenant))
        ->post(route('business.establishments.permissions.restore', ['tenant' => $tenant, 'version' => $version]), ['motif' => 'x'])
        ->assertNotFound();

    Http::assertNothingSent();
});

test("l'historique montre ce qui a changé d'une version à l'autre", function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);
    $tenant = etablissementV2();
    PermissionMatrixVersion::create(['tenant_id' => $tenant->id, 'numero' => 0, 'nature' => 'etat_initial', 'ecarts' => []]);
    PermissionMatrixVersion::create(['tenant_id' => $tenant->id, 'numero' => 1, 'motif' => 'Magasinier autonome',
        'ecarts' => [['role' => 'storekeeper', 'permission' => 'economat.items.creer', 'effect' => 'allow']]]);

    $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', ['tenant' => $tenant, 'onglet' => 'historique']))
        ->assertOk()
        ->assertSee('Magasinier autonome')
        ->assertSee('+ storekeeper|economat.items.creer → allow')
        ->assertSee('Revenir à cette version');
});

// ── Comptes administrateurs ─────────────────────────────────────────────────

test("créer un administrateur passe par l'API ; le mot de passe tiré n'est montré qu'une fois", function () {
    Http::fake(['*' => Http::response(['id' => 12], 201)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->post(route('business.establishments.admins.store', $tenant), [
            'name' => 'Paul Essomba', 'email' => 'paul@hotel.test',
        ])
        ->assertSessionHas('success')
        ->assertSessionHas('identifiants', fn ($i) => $i['email'] === 'paul@hotel.test' && strlen($i['password']) === 14);

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r->url() === 'http://meka-erp-zingana-app/api/comptes/administrateurs'
        && strlen($r['password']) === 14
        && $r['name'] === 'Paul Essomba');
});

test('un mot de passe saisi de moins de huit caractères est refusé avant de partir', function () {
    Http::fake();
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->post(route('business.establishments.admins.store', $tenant), [
            'name' => 'Paul Essomba', 'email' => 'paul@hotel.test', 'password' => 'court',
        ])
        ->assertSessionHasErrors('password');

    Http::assertNothingSent();
});

test("désactiver un administrateur passe par l'API", function () {
    Http::fake(['*' => Http::response(['id' => 5, 'actif' => false], 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->post(route('business.establishments.admins.update', ['tenant' => $tenant, 'compte' => 5]), [
            'action' => 'desactiver', 'nom' => 'Paul Essomba',
        ])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_ends_with($r->url(), '/api/comptes/administrateurs/5')
        && $r['actif'] === false);
});

test("un refus de l'établissement est rapporté", function () {
    Http::fake(['*' => Http::response([
        'message' => 'Adresse prise.', 'errors' => ['email' => ["Cette adresse est déjà celle d'un compte de l'établissement."]],
    ], 422)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->post(route('business.establishments.admins.store', $tenant), ['name' => 'Paul', 'email' => 'paul@hotel.test'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'déjà celle'));
});

test("un propriétaire étranger ne gère pas les administrateurs d'un autre", function () {
    Http::fake();
    $tenant = etablissementV2();

    $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
        ->post(route('business.establishments.admins.store', $tenant), ['name' => 'Intrus', 'email' => 'intrus@hotel.test'])
        ->assertForbidden();

    Http::assertNothingSent();
});

test("l'onglet des administrateurs liste ceux de l'établissement", function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', ['tenant' => $tenant, 'onglet' => 'administrateurs']))
        ->assertOk()
        ->assertSee('Paul Essomba')
        ->assertSee('Créer un compte administrateur')
        ->assertSee('Réinitialiser le mot de passe');
});

test('les alertes reprennent la revue des comptes de l\'établissement', function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);
    $tenant = etablissementV2();

    $this->actingAs(proprietaireDe($tenant))
        ->get(route('business.establishments.permissions', ['tenant' => $tenant, 'onglet' => 'alertes']))
        ->assertOk()
        ->assertSee('Aucun compte en comptabilité.');
});

// ── Onglet Rôles de la console technique ────────────────────────────────────

test("l'onglet Rôles lit le référentiel et les effectifs dans les établissements", function () {
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);
    etablissementV2();
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $reponse = $this->actingAs($admin)->getJson(route('tech.roles.distribution'))->assertOk();

    $referentiel = collect($reponse->json('referentiel'))->keyBy('slug');
    expect($referentiel['econome']['name'])->toBe('Chef économe')
        ->and($reponse->json('establishments.0.roles.reception'))->toBe(2)
        ->and($reponse->json('establishments.0.version'))->toBe(2);
});
