<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\ModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Répertoire des modules : l'onglet du tableau de bord et la fiche de chaque
 * module. Le point sensible est l'onglet — la chaîne de conditions du
 * tableau de bord se termine par un « autres onglets » fourre-tout qui affiche
 * un contenu générique. Une branche placée après lui n'est jamais atteinte,
 * sans que rien ne casse ni ne remonte d'erreur.
 */

function catalogueAdmin(): User
{
    return User::factory()->create([
        'role'      => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);
}

function cataloguePatron(): User
{
    return User::factory()->create([
        'role'      => User::ROLE_OWNER,
        'is_active' => true,
    ]);
}

function catalogueEtablissement(string $nom, array $modules): Tenant
{
    return Tenant::create([
        'name'      => $nom,
        'slug'      => Str::slug($nom) . '-' . random_int(1, 99999),
        'db_name'   => 'db_' . random_int(1000, 99999),
        'owner_id'  => cataloguePatron()->id,
        'modules'   => $modules,
        'is_active' => true,
    ]);
}

// ── Onglet du tableau de bord ────────────────────────────────────────────────

test('l\'onglet modules affiche le répertoire et non le contenu générique', function () {
    $response = $this->actingAs(catalogueAdmin())
        ->get(route('tech.dashboard', ['tab' => 'modules']));

    $response->assertOk()
        ->assertSee('Répertoire des modules')
        ->assertSee(route('tech.modules.show', 'restaurant'))
        // Le placeholder des onglets sans écran dédié : s'il apparaît, la
        // branche du répertoire est passée derrière le fourre-tout.
        ->assertDontSee('Service backend');
});

test('chaque module du catalogue a sa carte dans l\'onglet', function () {
    $response = $this->actingAs(catalogueAdmin())
        ->get(route('tech.dashboard', ['tab' => 'modules']));

    foreach (array_keys(ModuleCatalog::all()) as $slug) {
        $response->assertSee(route('tech.modules.show', $slug));
    }
});

// ── Fiche d'un module ────────────────────────────────────────────────────────

test('chaque fiche de module expose son guide d\'utilisation', function () {
    $admin = catalogueAdmin();

    foreach (ModuleCatalog::all() as $slug => $module) {
        $this->actingAs($admin)
            ->get(route('tech.modules.show', $slug))
            ->assertOk()
            // Libellé escapé par Blade (« & » devient « &amp; ») : on laisse
            // assertSee échapper le besoin de la même manière.
            ->assertSee($module['label'])
            ->assertSee('Guide d\'utilisation', false)
            ->assertSee('Écrans du module', false);
    }
});

test('un module inconnu renvoie une page introuvable', function () {
    $this->actingAs(catalogueAdmin())
        ->get(route('tech.modules.show', 'teleportation'))
        ->assertNotFound();
});

test('la fiche d\'un module est réservée à l\'administrateur technique', function () {
    $this->get(route('tech.modules.show', 'restaurant'))->assertRedirect(route('login'));

    $this->actingAs(cataloguePatron())
        ->get(route('tech.modules.show', 'restaurant'))
        ->assertRedirect()
        ->assertSessionHas('access_denied_popup');
});

// ── Établissements équipés ───────────────────────────────────────────────────

test('la fiche ne liste que les établissements où le module est actif', function () {
    catalogueEtablissement('Villa Boutanga', ['restaurant', 'shop']);
    catalogueEtablissement('Weloobe', ['restaurant']);

    $this->actingAs(catalogueAdmin())
        ->get(route('tech.modules.show', 'shop'))
        ->assertOk()
        ->assertSee('Villa Boutanga')
        ->assertDontSee('Weloobe');
});

test('un établissement jamais passé par le sélecteur compte comme tout équipé', function () {
    // Aucune clé canonique enregistrée : côté application, tous les modules
    // répondent. Le répertoire doit refléter cet état, pas un zéro trompeur.
    catalogueEtablissement('Résidence Historique', []);

    $this->actingAs(catalogueAdmin())
        ->get(route('tech.modules.show', 'ledger'))
        ->assertOk()
        ->assertSee('Résidence Historique');
});

test('un module cœur est présenté comme actif partout', function () {
    catalogueEtablissement('Villa Boutanga', ['restaurant']);

    $this->actingAs(catalogueAdmin())
        ->get(route('tech.modules.show', 'economat'))
        ->assertOk()
        ->assertSee('Villa Boutanga')
        ->assertSee('Livré avec toute application', false);
});
