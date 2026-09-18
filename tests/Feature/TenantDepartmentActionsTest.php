<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function departmentTestTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Hôtel Test Département',
        'slug' => 'hotel-test-'.random_int(1000, 99999),
        'db_name' => 'db_test_'.random_int(1000, 99999),
        'owner_id' => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'is_active' => true,
        'users_count' => 5,
    ]);
}

class DepartmentTestTenantDatabase extends TenantDatabase
{
    public array $departments = [];

    public array $requetes = [];

    public function createDepartment(Tenant $tenant, array $data, array $modules = []): int
    {
        $id = count($this->departments) + 1;
        $this->departments[$id] = array_merge($data, ['id' => $id, 'modules' => $modules]);
        $this->requetes[] = ['action' => 'create', 'data' => $data, 'modules' => $modules];

        return $id;
    }

    public function updateDepartment(Tenant $tenant, int $id, array $data, array $modules = []): void
    {
        $this->departments[$id] = array_merge($data, ['id' => $id, 'modules' => $modules]);
        $this->requetes[] = ['action' => 'update', 'id' => $id, 'data' => $data, 'modules' => $modules];
    }

    public function deleteDepartment(Tenant $tenant, int $id): void
    {
        unset($this->departments[$id]);
        $this->requetes[] = ['action' => 'delete', 'id' => $id];
    }

    public function createUser(Tenant $tenant, array $userData, array $roleSlugs = [], array $levels = [], ?int $departmentId = null): int
    {
        $id = 42;
        $this->requetes[] = ['action' => 'createUser', 'data' => $userData, 'roles' => $roleSlugs, 'levels' => $levels, 'departmentId' => $departmentId];

        return $id;
    }

    public function connect(Tenant $tenant): PDO
    {
        return new class extends PDO
        {
            public function __construct() {}

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                return new class extends PDOStatement
                {
                    public function execute(?array $params = null): bool
                    {
                        return true;
                    }

                    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed
                    {
                        return false;
                    }
                };
            }
        };
    }
}

test('un administrateur technique peut créer un département avec ses modules associés', function () {
    $tenant = departmentTestTenant();
    $double = new DepartmentTestTenantDatabase;
    app()->instance(TenantDatabase::class, $double);

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $reponse = $this->actingAs($admin)
        ->post(route('tech.establishments.departments.store', $tenant), [
            'name' => 'Réception & Accueil',
            'code' => 'REC',
            'description' => 'Accueil et gestion des réservations',
            'icon' => 'calendar-check',
            'accent' => 'sky',
            'sort_order' => 2,
            'modules' => ['reservations', 'hebergement'],
            'levels' => [
                'reservations' => 'write',
                'hebergement' => 'read',
            ],
        ]);

    $reponse->assertRedirect();
    $reponse->assertSessionHas('success');

    expect($double->requetes)->toHaveCount(1)
        ->and($double->requetes[0]['action'])->toBe('create')
        ->and($double->requetes[0]['data']['name'])->toBe('Réception & Accueil')
        ->and($double->requetes[0]['modules'])->toBe([
            'reservations' => 'write',
            'hebergement' => 'read',
        ]);
});

test('un administrateur technique peut modifier un département et ajuster les modules', function () {
    $tenant = departmentTestTenant();
    $double = new DepartmentTestTenantDatabase;
    app()->instance(TenantDatabase::class, $double);

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $reponse = $this->actingAs($admin)
        ->put(route('tech.establishments.departments.update', ['tenant' => $tenant, 'department' => 3]), [
            'name' => 'Hébergement & Housekeeping',
            'code' => 'HSK',
            'description' => 'Entretien des chambres',
            'icon' => 'sparkles',
            'accent' => 'teal',
            'sort_order' => 3,
            'modules' => ['housekeeping', 'economat'],
            'levels' => [
                'housekeeping' => 'write',
                'economat' => 'write',
            ],
        ]);

    $reponse->assertRedirect();
    $reponse->assertSessionHas('success');

    expect($double->requetes)->toHaveCount(1)
        ->and($double->requetes[0]['action'])->toBe('update')
        ->and($double->requetes[0]['id'])->toBe(3)
        ->and($double->requetes[0]['data']['name'])->toBe('Hébergement & Housekeeping');
});

test('un administrateur technique peut supprimer un département', function () {
    $tenant = departmentTestTenant();
    $double = new DepartmentTestTenantDatabase;
    app()->instance(TenantDatabase::class, $double);

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $reponse = $this->actingAs($admin)
        ->delete(route('tech.establishments.departments.destroy', ['tenant' => $tenant, 'department' => 5]));

    $reponse->assertRedirect();
    $reponse->assertSessionHas('success');

    expect($double->requetes)->toHaveCount(1)
        ->and($double->requetes[0]['action'])->toBe('delete')
        ->and($double->requetes[0]['id'])->toBe(5);
});

test('un administrateur peut créer un utilisateur avec son département et ses rôles', function () {
    $tenant = departmentTestTenant();
    $double = new DepartmentTestTenantDatabase;
    app()->instance(TenantDatabase::class, $double);

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $reponse = $this->actingAs($admin)
        ->post(route('tech.establishments.users.store', $tenant), [
            'name' => 'Marie Dupont',
            'email' => 'marie.dupont@test.com',
            'phone' => '+237699112233',
            'password' => 'Secret123!',
            'role' => 'receptionniste',
            'department_id' => 2,
            'roles' => ['receptionniste'],
            'levels' => ['receptionniste' => 'write'],
            'is_active' => 1,
        ]);

    $reponse->assertRedirect();
    $reponse->assertSessionHas('success');

    expect($double->requetes)->toHaveCount(1)
        ->and($double->requetes[0]['action'])->toBe('createUser')
        ->and($double->requetes[0]['data']['name'])->toBe('Marie Dupont')
        ->and($double->requetes[0]['data']['department_id'])->toBe(2)
        ->and($double->requetes[0]['roles'])->toBe(['receptionniste']);
});
