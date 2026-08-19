<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Actions sur les employés d'un établissement.
 *
 * Ces employés vivent dans la base de leur établissement, jointe par PDO. Les
 * tests ci-dessous n'ouvrent pas de conteneur : ils remplacent le service
 * d'accès par un double, pour vérifier les autorisations et le comportement
 * du contrôleur — c'est là que se jouent les erreurs, pas dans le SQL.
 */

function actionTenant(): Tenant
{
    return Tenant::create([
        'name'        => 'Villa Boutanga',
        'slug'        => 'villa-b-' . random_int(1, 99999),
        'db_name'     => 'db_' . random_int(1000, 99999),
        'owner_id'    => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'is_active'   => true,
        'users_count' => 3,
    ]);
}

/** Double du service : mémorise les écritures au lieu de joindre un conteneur. */
class TenantDatabaseDouble extends TenantDatabase
{
    public array $employes = [];
    public array $requetes = [];
    public bool $joignable = true;

    public function connect(Tenant $tenant): PDO
    {
        if (!$this->joignable) {
            throw new PDOException('Conteneur injoignable');
        }

        return new class($this) extends PDO {
            public function __construct(private $double) {}
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $double = $this->double;

                return new class($double, $query) extends PDOStatement {
                    public function __construct(private $double, private string $query) {}
                    public function execute(?array $params = null): bool
                    {
                        $this->double->requetes[] = ['sql' => $this->query, 'params' => $params];
                        return true;
                    }
                    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed
                    {
                        return false;   // aucun doublon d'email
                    }
                };
            }
            public function query(string $query, ?int $fetchMode = null, mixed ...$fetch): PDOStatement|false
            {
                $double = $this->double;

                return new class($double, $query) extends PDOStatement {
                    public function __construct(private $double, private string $query) {}
                    public function fetchColumn(int $column = 0): mixed
                    {
                        // Deux managers : la suppression du dernier reste testable à part.
                        return 2;
                    }
                };
            }
            public function beginTransaction(): bool { return true; }
            public function commit(): bool { return true; }
            public function rollBack(): bool { return true; }
            public function inTransaction(): bool { return false; }
        };
    }

    public function findUser(Tenant $tenant, int $userId): ?object
    {
        return $this->employes[$userId] ?? null;
    }

    /** La fiche lit davantage de colonnes : on complète l'employé du double. */
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
            'last_login_at'     => '2026-08-01 09:30:00',
            'email_verified_at' => null,
            'created_at'        => '2026-05-12 08:00:00',
            'updated_at'        => '2026-08-01 09:30:00',
            'roles'             => $this->rolesAffectes,
        ]);
    }

    /** @var array<int, array<string, mixed>> Rôles rattachés à l'employé consulté. */
    public array $rolesAffectes = [];

    /** @var array<int, object> Rôles proposés à l'affectation. */
    public array $rolesAssignables = [];

    public function assignableRoles(Tenant $tenant): array
    {
        return $this->rolesAssignables;
    }

    public int $managers = 2;

    public function activeManagerCount(Tenant $tenant): int
    {
        return $this->managers;
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

// ── Autorisations ─────────────────────────────────────────────────────────────

test('un propriétaire ne gère que les employés de ses propres établissements', function () {
    $tenant = actionTenant();
    doubleService([7 => employe(7)]);

    $etranger = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);

    $this->actingAs($etranger)
        ->post(route('tech.establishments.users.toggle-active', ['tenant' => $tenant, 'user' => 7]));

    // Le middleware « tech_admin » de la route écarte déjà tout propriétaire ;
    // l'autorisation du contrôleur en est la seconde barrière.
    expect(app(TenantDatabase::class)->requetes)->toBeEmpty();
});

test('un éditeur de contenu ne touche pas aux employés', function () {
    $tenant = actionTenant();
    doubleService([7 => employe(7)]);

    $editeur = User::factory()->create([
        'role' => User::ROLE_SITE_EDITOR, 'tenant_id' => $tenant->id, 'is_active' => true,
    ]);

    $this->actingAs($editeur)
        ->delete(route('tech.establishments.users.destroy', ['tenant' => $tenant, 'user' => 7]));

    expect(app(TenantDatabase::class)->requetes)->toBeEmpty();
});

// ── Activation ────────────────────────────────────────────────────────────────

test('désactiver un employé écrit dans la base de son établissement', function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7, 'Serge Mbarga', 'reception', true)]);
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('tech.establishments.users.toggle-active', ['tenant' => $tenant, 'user' => 7]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $ecriture = collect($double->requetes)->firstWhere(fn ($r) => str_contains($r['sql'], 'UPDATE users SET is_active'));

    expect($ecriture)->not->toBeNull()
        // « false » : l'employé était actif, on le désactive.
        ->and($ecriture['params'])->toBe(['false', 7]);
});

test('un employé introuvable est signalé sans écriture', function () {
    $tenant = actionTenant();
    $double = doubleService([]);   // aucun employé
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('tech.establishments.users.toggle-active', ['tenant' => $tenant, 'user' => 999]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($double->requetes)->toBeEmpty();
});

test('une base injoignable donne un message clair plutôt qu\'une erreur brute', function () {
    $tenant = actionTenant();
    doubleService([7 => employe(7)], joignable: false);
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('tech.establishments.users.toggle-active', ['tenant' => $tenant, 'user' => 7]))
        ->assertRedirect()
        ->assertSessionHas('error');

    // Le détail technique n'a pas à remonter à l'écran : ce qui est
    // actionnable, c'est « démarrez les conteneurs ».
    expect(session('error'))->toContain('conteneurs');
});

// ── Suppression ───────────────────────────────────────────────────────────────

test('supprimer un employé le retire de la base du tenant', function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7, 'Serge Mbarga', 'reception')]);
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('tech.establishments.users.destroy', ['tenant' => $tenant, 'user' => 7]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(collect($double->requetes)->contains(fn ($r) => str_contains($r['sql'], 'DELETE FROM users')))->toBeTrue()
        // Le compteur dénormalisé suit la suppression.
        ->and($tenant->fresh()->users_count)->toBe(2);
});

// ── Modification et accès ─────────────────────────────────────────────────────

test('modifier sans mot de passe ne touche pas au mot de passe', function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7)]);
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('tech.establishments.users.update', ['tenant' => $tenant, 'user' => 7]), [
            'name' => 'Serge Mbarga', 'email' => 'serge@example.com', 'phone' => '+237677000000',
            'password' => '',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $update = collect($double->requetes)->first(fn ($r) => str_contains($r['sql'], 'UPDATE users SET name'));

    expect($update)->not->toBeNull()
        ->and($update['sql'])->not->toContain('password');
});

test('les rôles cochés sont écrits dans le pivot avec leur niveau', function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7)]);
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('tech.establishments.users.update', ['tenant' => $tenant, 'user' => 7]), [
            'name' => 'Serge Mbarga', 'email' => 'serge@example.com',
            'roles'  => [3, 5],
            'levels' => [3 => 'read', 5 => 'write'],
        ])
        ->assertRedirect();

    // Les rôles précédents sont d'abord effacés : l'écran présente toujours
    // l'ensemble des rôles, donc une case décochée vaut retrait.
    expect(collect($double->requetes)->contains(fn ($r) => str_contains($r['sql'], 'DELETE FROM role_user')))->toBeTrue();

    $inserts = collect($double->requetes)
        ->filter(fn ($r) => str_contains($r['sql'], 'INSERT INTO role_user'))
        ->pluck('params')->values()->all();

    expect($inserts)->toBe([[7, 3, 'read'], [7, 5, 'write']]);
});

test('un rôle coché sans niveau précisé donne l\'écriture', function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7)]);
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('tech.establishments.users.update', ['tenant' => $tenant, 'user' => 7]), [
            'name' => 'Serge Mbarga', 'email' => 'serge@example.com', 'roles' => [4],
        ])
        ->assertRedirect();

    $insert = collect($double->requetes)->first(fn ($r) => str_contains($r['sql'], 'INSERT INTO role_user'));

    expect($insert['params'])->toBe([7, 4, 'write']);
});

// ── Fiche détaillée ───────────────────────────────────────────────────────────

test('la fiche d\'un employé affiche ses informations et ses accès', function () {
    $tenant = actionTenant();
    $double = doubleService([7 => employe(7)]);
    $double->rolesAffectes = [
        ['id' => 3, 'slug' => 'reception', 'name' => 'Réception', 'module' => 'hebergement',
         'description' => 'Arrivées et départs', 'level' => 'read'],
    ];
    $double->rolesAssignables = [
        (object) ['id' => 3, 'name' => 'Réception', 'slug' => 'reception', 'description' => 'Arrivées et départs', 'module' => 'hebergement', 'icon' => null],
        (object) ['id' => 5, 'name' => 'Caisse', 'slug' => 'cashier', 'description' => null, 'module' => 'boutique', 'icon' => null],
    ];

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 7]))
        ->assertOk()
        ->assertSee('Serge Mbarga')
        ->assertSee('serge@example.com')
        ->assertSee('Accès par module')
        // Le rôle déjà attribué et celui qui ne l'est pas figurent tous deux :
        // l'écran présente l'ensemble, l'absence de case valant retrait.
        ->assertSee('Réception')
        ->assertSee('Caisse');
});

test('la fiche est accessible pour un manager comme pour tout autre employé', function () {
    $tenant = actionTenant();
    $double = doubleService([9 => employe(9, 'Alice Ngo', 'manager')]);
    $double->managers = 3;

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 9]))
        ->assertOk()
        ->assertSee('Alice Ngo')
        ->assertSee('Supprimer définitivement');
});

test('le dernier manager ne peut pas être supprimé depuis sa fiche', function () {
    $tenant = actionTenant();
    $double = doubleService([9 => employe(9, 'Alice Ngo', 'manager')]);
    $double->managers = 1;

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    // Le refus se dit avant le clic plutôt qu'après : le bouton cède la place
    // à l'explication.
    $this->actingAs($admin)
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 9]))
        ->assertOk()
        ->assertSee('Suppression impossible')
        ->assertDontSee('Supprimer définitivement');
});

test('un employé inconnu renvoie à la liste avec un message', function () {
    $tenant = actionTenant();
    doubleService([]);

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
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

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => 7]))
        ->assertRedirect()
        ->assertSessionHas('error');
});
