<?php

/**
 * Présence de la marque Wetchah ERP dans la console.
 *
 * Elle n'y figurait nulle part : les écrans s'annonçaient en texte brut, et
 * deux d'entre eux nommaient encore « Villa Boutanga » en dur — le premier
 * établissement, devenu par accident le titre du produit.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test("la racine mène à la connexion, qui porte le logo", function () {
    // welcome.blade.php n'est jamais servi : « / » redirige. La vue a été
    // remise d'aplomb — elle nommait « Villa Boutanga » — mais c'est la page
    // de connexion qui accueille réellement.
    $this->get('/')->assertRedirect(route('login'));

    $this->get(route('login'))->assertOk()->assertSee('images/logo-erp.png', false);
});

test("plus aucun écran ne nomme un établissement en dur", function () {
    foreach ([resource_path('views/welcome.blade.php'),
              resource_path('views/admin/dashboard.blade.php')] as $vue) {
        expect(file_get_contents($vue))->not->toContain('Villa Boutanga');
    }
});

test("la connexion à l'administration porte le logo complet", function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('images/logo-erp.png', false)
        ->assertSee('Wetchah ERP');
});

test('le tableau de bord porte la marque et ramène chez lui', function () {
    $this->actingAs(User::factory()->create(['role' => 'tech_admin']))
        ->get(route('tech.dashboard'))
        ->assertOk()
        // La marque seule : le mot du lockup serait illisible à la hauteur
        // d'une barre de navigation.
        ->assertSee('images/logo-erp-mark.png', false)
        ->assertDontSee('Villa Boutanga');
});

test("les onglets ne nomment plus un établissement en dur", function () {
    $this->actingAs(User::factory()->create(['role' => 'tech_admin']))
        ->get(route('tech.dashboard'))
        ->assertSee('<title>Wetchah ERP — Administration</title>', false);
});

test("chaque écran de la console déclare son icône d'onglet", function () {
    $this->actingAs(User::factory()->create(['role' => 'tech_admin']));

    foreach ([route('tech.dashboard'), route('tech.owners.index')] as $url) {
        $this->get($url)
            ->assertSee('apple-touch-icon.png', false)
            ->assertSee('favicon.ico', false);
    }
});

test('les fichiers du logo existent et sont servis', function (string $fichier) {
    expect(file_exists(public_path($fichier)))->toBeTrue("{$fichier} manquant")
        ->and(filesize(public_path($fichier)))->toBeGreaterThan(0);
})->with([
    'images/logo-erp.png',
    'images/logo-erp-mark.png',
    'images/apple-touch-icon.png',
    'favicon.ico',
]);

test("le logo reste léger : il est chargé sur chaque écran", function () {
    // L'original fait 966 Ko. Le servir tel quel sur toutes les pages de la
    // console coûterait plus que tout le reste de leurs ressources.
    expect(filesize(public_path('images/logo-erp.png')))->toBeLessThan(250 * 1024)
        ->and(filesize(public_path('images/logo-erp-mark.png')))->toBeLessThan(250 * 1024);
});
