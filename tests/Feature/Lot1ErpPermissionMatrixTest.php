<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['provisioning.reporting_secret' => 'jeton-de-service']);
});

function tenantEtablissementLot1(): Tenant
{
    return etablissementValide([
        'slug'                 => 'zingana-lot1-test',
        'docker_app_container' => 'meka-erp-zingana-app',
        'provisioned_at'       => now(),
    ]);
}

test("la matrice de droits dans l'ERP reflète fidèlement les ajustements du Lot 1 du PMS", function () {
    // Matrice renvoyée par le PMS suite au Lot 1
    $matriceLot1 = [
        'version'   => 2,
        'catalogue' => [
            // S7 : Achats créent uniquement, contrôleur modifie/supprime
            'economat.items.creer'      => ['purchasing_manager'],
            'economat.items.modifier'   => ['controller'],
            'economat.items.supprimer'  => ['controller'],
            'economat.items.adjust'     => ['controller'],
            'economat.suppliers.creer'  => ['purchasing_manager'],
            'economat.suppliers.modifier'  => ['controller'],
            'economat.suppliers.supprimer' => ['controller'],
            // S8 : Magasinier et économe n'ont pas accès aux bons de commande
            'economat.orders.voir'      => ['controller', 'manager', 'purchasing_manager'],
            'economat.orders.export'    => ['controller', 'manager', 'purchasing_manager'],
            // S6 : Actes exclusifs sans l'admin
            'accounting.cash_reviews.creer' => ['accountant'],
        ],
        'modules'   => ['economat', 'accounting'],
        'ecritures' => [
            'economat.items.creer', 'economat.items.modifier', 'economat.items.supprimer', 'economat.items.adjust',
            'economat.suppliers.creer', 'economat.suppliers.modifier', 'economat.suppliers.supprimer',
            'accounting.cash_reviews.creer',
        ],
        'roles'     => [
            // Admin figé
            ['slug' => 'admin', 'name' => 'Administrateur', 'module' => 'it', 'is_assignable' => false, 'level' => 1, 'statut' => 'actif', 'reglable' => false, 'titulaires' => 1],
            // Manager
            ['slug' => 'manager', 'name' => 'Manager', 'module' => 'direction', 'is_assignable' => false, 'level' => 2, 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
            // Contrôleur de gestion (porte les prérogatives qualité et exclusivités référentiel)
            ['slug' => 'controller', 'name' => 'Contrôleur de gestion', 'module' => 'comptabilite', 'is_assignable' => true, 'level' => null, 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
            // Rôle quality_auditor RETIRÉ : non réglable, sans colonne dans la grille
            ['slug' => 'quality_auditor', 'name' => 'Contrôleur Qualité & Audit', 'module' => 'qualite', 'is_assignable' => false, 'level' => null, 'statut' => 'retire', 'reglable' => false, 'titulaires' => 0],
            // Achats
            ['slug' => 'purchasing_manager', 'name' => 'Responsable des achats', 'module' => 'economat', 'is_assignable' => true, 'level' => 3, 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
            // Économe & Magasinier
            ['slug' => 'econome', 'name' => 'Chef économe', 'module' => 'economat', 'is_assignable' => true, 'level' => 3, 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
            ['slug' => 'storekeeper', 'name' => 'Magasinier', 'module' => 'economat', 'is_assignable' => true, 'level' => 4, 'statut' => 'actif', 'reglable' => true, 'titulaires' => 1],
        ],
        'ecarts'    => [],
        'exceptions_echues' => [],
        'restrictions' => [],
        'comptes'   => [
            ['id' => 1, 'name' => 'Admin Tech', 'email' => 'admin@hotel.test', 'phone' => null, 'roles' => ['admin'], 'lecture_seule' => [], 'actif' => true, 'departement' => null, 'derniere_connexion' => null],
            ['id' => 2, 'name' => 'Martin Contrôleur', 'email' => 'martin@hotel.test', 'phone' => null, 'roles' => ['controller'], 'lecture_seule' => [], 'actif' => true, 'departement' => null, 'derniere_connexion' => null],
        ],
        'constats'  => [],
        'incompatibilites' => [],
        'regles_de_cumul' => [],
        'cumuls'    => [],
        'portees'   => [],
        'droits_bornes' => [],
        'empreinte' => 'empreinte-lot1',
    ];

    Http::fake(['*/api/permissions/matrice' => Http::response($matriceLot1, 200)]);
    $tenant = tenantEtablissementLot1();

    $reponse = $this->actingAs(User::find($tenant->owner_id))
        ->get(route('business.establishments.permissions', $tenant))
        ->assertOk();

    // 1. quality_auditor retiré n'apparaît pas comme colonne réglable
    $reponse->assertDontSee('data-colonne="quality_auditor"', false);

    // 2. controller apparaît bien dans les colonnes
    $reponse->assertSee('data-colonne="controller"', false);

    // 3. purchasing_manager apparaît bien dans les colonnes
    $reponse->assertSee('data-colonne="purchasing_manager"', false);

    // 4. Les droits réalignés du catalogue PMS sont présents dans la grille
    $reponse->assertSee('economat.items.adjust')
        ->assertSee('economat.items.creer')
        ->assertSee('economat.items.modifier')
        ->assertSee('economat.orders.voir');
});

test("le jeu de données staff.json de l'ERP affecte le rôle controller au département qualité", function () {
    $jeu = json_decode(file_get_contents(base_path('demo/data/staff.json')), true);

    $qualiteStaff = array_filter(
        $jeu['staff'],
        fn ($p) => ($p['department'] ?? '') === 'qualite_controle'
    );

    expect($qualiteStaff)->not->toBeEmpty();

    foreach ($qualiteStaff as $membre) {
        expect($membre['role'])->toBe('controller')
            ->and($membre['role'])->not->toBe('quality_auditor');
    }
});
