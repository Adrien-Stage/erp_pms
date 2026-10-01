<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Le personnel d'un établissement, vu depuis l'ERP.
 *
 * La console consulte : les comptes se créent et se modifient dans
 * l'application, par l'administrateur de l'établissement. Elle n'écrit plus
 * dans la base du tenant — une écriture directe contournait l'application, qui
 * ne pouvait ni la valider ni la tracer. Reste l'accès au portail GRC d'un
 * contrôleur de gestion, dont la base est à part.
 */

function actionTenant(array $attributs = []): Tenant
{
    return Tenant::create(array_merge([
        'name'        => 'Villa Boutanga',
        'slug'        => 'villa-b-' . random_int(1, 99999),
        'db_name'     => 'db_' . random_int(1000, 99999),
        'owner_id'    => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'is_active'   => true,
        'users_count' => 3,
    ], $attributs));
}

/** Double du service de lecture : la console ne fait plus que lire. */
class TenantDatabaseDouble extends TenantDatabase
{
    public array $employes = [];
    public bool $joignable = true;
    public int $connexions = 0;

    /** @var array<int, array<string, mixed>> Rôles rattachés à l'employé consulté. */
    public array $rolesAffectes = [];

    /** @var array<string, string> Restrictions de service de l'employé consulté. */
    public array $restrictions = [];

    public function connect(Tenant $tenant): PDO
    {
        // Toute connexion brute serait le signe d'une écriture SQL directe.
        $this->connexions++;
        throw new PDOException('Aucune connexion brute attendue');
    }

    public function users(Tenant $tenant): array
    {
        if (!$this->joignable) {
            throw new PDOException('Conteneur injoignable');
        }

        return array_map(fn ($e) => (object) ((array) $e + ['roles' => $this->rolesAffectes, 'module_permissions' => []]), $this->employes);
    }

    public function departments(Tenant $tenant): array
    {
        return [];
    }

    public function userDetail(Tenant $tenant, int $userId): ?object
    {
        if (!$this->joignable) {
            throw new PDOException('Conteneur injoignable');
        }

        $employe = $this->employes[$userId] ?? null;

        if (!$employe) {
            return null;
        }

        return (object) ((array) $employe + [
            'last_login_at'      => '2026-08-01 09:30:00',
            'email_verified_at'  => null,
            'created_at'         => '2026-05-12 08:00:00',
            'updated_at'         => '2026-08-01 09:30:00',
            'roles'              => $this->rolesAffectes,
            'module_permissions' => $this->restrictions,
            'department_id'      => null,
            'department_name'    => null,
        ]);
    }
}

function doubleService(array $employes = [], bool $joignable = true): TenantDatabaseDouble
{
    $double = new TenantDatabaseDouble();
    $double->employes = $employes;
    $double->joignable = $joignable;
    app()->instance(TenantDatabase::class, $double);

    return $double;
}

function employe(int $id, string $nom = 'Serge Mbarga', string $role = 'reception', bool $actif = true): object
{
    return (object) [
        'id' => $id, 'name' => $nom, 'email' => strtolower(explode(' ', $nom)[0]) . '@example.com',
        'phone' => '+237699112233', 'role' => $role, 'is_active' => $actif,
    ];
}

function administrateurTechnique(): User
{
    return User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
}

// ── Plus aucune écriture directe ────────────────────────────────────────────

test("la console n'écrit plus de compte dans la base de l'établissement", function () {
    foreach ([
        'tech.establishments.users.store', 'tech.establishments.users.update',
        'tech.establishments.users.toggle-active', 'tech.establishments.users.destroy',
        'tech.establishments.create-manager', 'tech.establishments.create-controller',
        'business.establishments.create-manager', 'business.establishments.create-controller',
    ] as $route) {
        expect(Route::has($route))->toBeFalse("La route {$route} existe encore.");
    }
});

test("la liste du personnel renvoie la gestion des comptes à l'administrateur", function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7)]);
    $double->rolesAffectes = [['slug' => 'reception_chief', 'name' => 'Chef de réception', 'module' => 'hebergement', 'level' => null]];

    $this->actingAs(administrateurTechnique())
        ->get(route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'users']))
        ->assertOk()
        ->assertSee('Serge Mbarga')
        // L'affectation fait foi, pas la colonne héritée « reception ».
        ->assertSee('Chef de réception')
        ->assertSee("se gèrent dans l'application", false)
        ->assertSee('Comptes administrateurs')
        ->assertDontSee('Créer un manager')
        ->assertDontSee('Ajouter un employé');

    expect($double->connexions)->toBe(0);
});

// ── Fiche détaillée ─────────────────────────────────────────────────────────

test("la fiche montre les rôles, leur niveau et les restrictions, sans rien modifier", function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7)]);
    $double->rolesAffectes = [
        ['id' => 3, 'slug' => 'reception', 'name' => 'Réceptionniste', 'module' => 'hebergement',
         'description' => 'Arrivées et départs', 'level' => 'read'],
    ];
    $double->restrictions = ['economat' => 'read', 'restaurant' => 'inherit'];

    $this->actingAs(administrateurTechnique())
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 7]))
        ->assertOk()
        ->assertSee('Serge Mbarga')
        ->assertSee('serge@example.com')
        ->assertSee('Réceptionniste')
        ->assertSee('Lecture seule')
        ->assertSee('Restrictions de service')
        ->assertSee('economat')
        // « inherit » ne retire rien : il n'est pas une restriction.
        ->assertDontSee('restaurant</span>', false)
        ->assertDontSee('Supprimer définitivement')
        ->assertDontSee('name="password"', false);
});

test("la fiche d'un contrôleur propose l'accès au portail GRC quand le module est actif", function () {
    $tenant = actionTenant(['modules' => ['grc']]);
    doubleService([8 => employe(8, 'Paul Atangana', 'controller')]);

    $this->actingAs(administrateurTechnique())
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 8]))
        ->assertOk()
        ->assertSee('Accès au portail GRC')
        ->assertSee(route('tech.establishments.users.grc', ['tenant' => $tenant, 'user' => 8]), false);
});

test("l'accès GRC est poussé au portail, sans toucher la base de l'établissement", function () {
    config(['provisioning.reporting_secret' => 'secret-de-service']);
    Http::fake(['*' => Http::response(['id' => 1], 201)]);

    $tenant = actionTenant(['modules' => ['grc'], 'docker_grc_container' => 'meka-erp-villa-grc']);
    $double = doubleService([8 => employe(8, 'Paul Atangana', 'controller')]);

    $this->actingAs(administrateurTechnique())
        ->post(route('tech.establishments.users.grc', ['tenant' => $tenant, 'user' => 8]), [
            'password' => 'portail-solide', 'password_confirmation' => 'portail-solide',
        ])
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'http://meka-erp-villa-grc:8000/')
        && $r['email'] === 'paul@example.com');
    expect($double->connexions)->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['event_type' => 'tenant_grc_access']);
});

test("l'accès GRC exige un mot de passe de huit caractères, confirmé", function () {
    Http::fake();
    $tenant = actionTenant(['modules' => ['grc']]);
    doubleService([8 => employe(8, 'Paul Atangana', 'controller')]);

    $this->actingAs(administrateurTechnique())
        ->post(route('tech.establishments.users.grc', ['tenant' => $tenant, 'user' => 8]), [
            'password' => 'court', 'password_confirmation' => 'court',
        ])
        ->assertSessionHasErrors('password');

    Http::assertNothingSent();
});

test('seul un contrôleur de gestion reçoit un accès au portail', function () {
    Http::fake();
    $tenant = actionTenant(['modules' => ['grc']]);
    doubleService([7 => employe(7)]);

    $this->actingAs(administrateurTechnique())
        ->post(route('tech.establishments.users.grc', ['tenant' => $tenant, 'user' => 7]), [
            'password' => 'portail-solide', 'password_confirmation' => 'portail-solide',
        ])
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('un employé inconnu renvoie à la liste avec un message', function () {
    $tenant = actionTenant();
    doubleService([]);

    $this->actingAs(administrateurTechnique())
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 404]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('un propriétaire n\'ouvre pas la fiche d\'un employé', function () {
    $tenant = actionTenant();
    doubleService([7 => employe(7)]);

    $etranger = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);

    // Le middleware « tech_admin » de la route renvoie le propriétaire vers son
    // propre espace plutôt que d'afficher un 403 : la fiche n'est jamais rendue.
    $reponse = $this->actingAs($etranger)
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 7]));

    $reponse->assertRedirect();
    expect($reponse->getContent())->not->toContain('Serge Mbarga');
});

test('une base injoignable renvoie un message plutôt qu\'une erreur brute', function () {
    $tenant = actionTenant();
    doubleService([7 => employe(7)], joignable: false);

    $this->actingAs(administrateurTechnique())
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 7]))
        ->assertRedirect()
        ->assertSessionHas('error');
});
