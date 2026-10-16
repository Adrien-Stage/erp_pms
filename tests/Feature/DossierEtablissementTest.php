<?php

/**
 * Dossier des docker-compose choisi à la création d'un établissement, avec son
 * sélecteur de dossiers du serveur.
 */

use App\Models\Tenant;
use App\Models\User;
use App\Services\DirectoryBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function racineDossiers(): string
{
    $racine = sys_get_temp_dir() . '/dossiers-test-' . random_int(1, 999999);
    mkdir($racine . '/alpha/profond', 0777, true);
    mkdir($racine . '/beta', 0777, true);
    mkdir($racine . '/.cache', 0777, true);
    file_put_contents($racine . '/fichier.txt', 'x');
    symlink('/tmp', $racine . '/lien');

    return realpath($racine);
}

function admin(): User
{
    return User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
}

test("le sélecteur ne liste que des sous-dossiers visibles, sans fichiers ni liens", function () {
    $racine = racineDossiers();

    $liste = app(DirectoryBrowser::class)->lister($racine);

    expect(array_column($liste['directories'], 'name'))->toBe(['alpha', 'beta'])
        ->and($liste['path'])->toBe($racine)
        ->and($liste['parent'])->toBe(dirname($racine))
        ->and($liste['writable'])->toBeTrue();
});

test("le sélecteur refuse un chemin relatif ou inexistant", function () {
    $nav = app(DirectoryBrowser::class);

    expect(fn () => $nav->lister('relatif/chemin'))->toThrow(InvalidArgumentException::class, 'absolu')
        ->and(fn () => $nav->lister('/chemin/qui/nexiste/pas'))->toThrow(InvalidArgumentException::class, "n'existe pas");
});

test("« .. » est résolu : on ne sort pas par un détour", function () {
    $racine = racineDossiers();

    expect(app(DirectoryBrowser::class)->lister($racine . '/alpha/../beta')['path'])->toBe($racine . '/beta');
});

test("l'API de navigation est réservée à l'administrateur TECH", function () {
    $racine = racineDossiers();

    $this->get(route('tech.directories.index', ['path' => $racine]))->assertRedirect();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
        ->get(route('tech.directories.index', ['path' => $racine]))->assertRedirect();

    $this->actingAs(admin())->getJson(route('tech.directories.index', ['path' => $racine]))
        ->assertOk()->assertJsonPath('path', $racine)->assertJsonPath('directories.0.name', 'alpha');
    $this->getJson(route('tech.directories.index', ['path' => '/introuvable-xyz']))->assertStatus(422);
});

test("on peut créer un sous-dossier, pas n'importe quel nom", function () {
    $racine = racineDossiers();
    $this->actingAs(admin());

    $this->postJson(route('tech.directories.store'), ['path' => $racine, 'name' => 'hotels'])
        ->assertCreated()->assertJsonPath('path', $racine . '/hotels');
    expect(is_dir($racine . '/hotels'))->toBeTrue();

    $this->postJson(route('tech.directories.store'), ['path' => $racine, 'name' => '../evasion'])->assertStatus(422);
    $this->postJson(route('tech.directories.store'), ['path' => $racine, 'name' => 'hotels'])->assertStatus(422);
});

test("sans choix, un établissement garde TENANTS_BASE_PATH ; avec un choix, son compose y va", function () {
    config(['provisioning.tenants_base_path' => '/var/wetchah/tenants']);

    $defaut = etablissementValide(['slug' => 'hotel-defaut']);
    expect($defaut->composePath())->toBe('/var/wetchah/tenants/.compose/hotel-defaut.yml');

    $choisi = etablissementValide(['slug' => 'hotel-choisi', 'compose_base_path' => '/srv/hotels/']);
    expect($choisi->composePath())->toBe('/srv/hotels/.compose/hotel-choisi.yml')
        ->and($choisi->baseDir())->toBe('/srv/hotels');
});

test("la création mémorise le dossier choisi et refuse un dossier inutilisable", function () {
    $racine = racineDossiers();
    $this->actingAs(admin());

    $base = [
        'owner_type' => 'new', 'owner_name' => 'Jean K', 'owner_email' => 'jk@exemple.cm', 'owner_nationality' => 'Camerounaise',
        'owner_password' => 'mot-de-passe-1', 'db_name' => 'dossier_db', 'app_port' => 18160,
        'name' => 'Hôtel Dossier', 'slug' => 'hotel-dossier', 'currency' => 'XAF',
    ];

    $this->post(route('tech.establishments.store'), $base + ['compose_base_path' => $racine . '/nexiste-pas'])
        ->assertSessionHasErrors('compose_base_path');
    $this->post(route('tech.establishments.store'), $base + ['compose_base_path' => 'relatif'])
        ->assertSessionHasErrors('compose_base_path');
    expect(Tenant::count())->toBe(0);

    $this->post(route('tech.establishments.store'), $base + ['compose_base_path' => $racine . '/alpha/'])->assertRedirect();
    expect(Tenant::firstWhere('slug', 'hotel-dossier')->compose_base_path)->toBe($racine . '/alpha');

    $this->post(route('tech.establishments.store'), array_merge($base, ['slug' => 'hotel-vide', 'db_name' => 'vide_db', 'app_port' => 18161, 'owner_email' => 'v@exemple.cm', 'compose_base_path' => '']))->assertRedirect();
    expect(Tenant::firstWhere('slug', 'hotel-vide')->compose_base_path)->toBeNull();
});

