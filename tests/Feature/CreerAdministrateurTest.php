<?php

/**
 * Commande erp:creer-admin : crée le compte administrateur technique d'une
 * console neuve, sans seeder ni tinker.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test("crée un administrateur technique actif dont le mot de passe est chiffré", function () {
    $this->artisan('erp:creer-admin', ['--nom' => 'Marie Abena', '--email' => 'marie@exemple.cm', '--mot-de-passe' => 'un-mot-de-passe-solide'])
        ->expectsOutputToContain('Compte créé')
        ->assertSuccessful();

    $u = User::where('email', 'marie@exemple.cm')->firstOrFail();
    expect($u->role)->toBe(User::ROLE_TECH_ADMIN)
        ->and($u->is_active)->toBeTrue()
        ->and(Hash::check('un-mot-de-passe-solide', $u->password))->toBeTrue();
});

test("refuse un mot de passe de moins de 12 caractères", function () {
    $this->artisan('erp:creer-admin', ['--nom' => 'A', '--email' => 'a@exemple.cm', '--mot-de-passe' => 'court'])
        ->expectsOutputToContain('au moins 12 caractères')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test("refuse une adresse déjà utilisée ou invalide", function () {
    User::create(['name' => 'X', 'email' => 'x@exemple.cm', 'password' => 'xxxxxxxxxxxxxxxx', 'role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->artisan('erp:creer-admin', ['--nom' => 'B', '--email' => 'x@exemple.cm', '--mot-de-passe' => 'un-mot-de-passe-solide'])
        ->expectsOutputToContain('déjà utilisée')->assertFailed();
    $this->artisan('erp:creer-admin', ['--nom' => 'B', '--email' => 'pas-un-courriel', '--mot-de-passe' => 'un-mot-de-passe-solide'])
        ->expectsOutputToContain('invalide')->assertFailed();

    expect(User::count())->toBe(1);
});

test("en mode interactif, le mot de passe est demandé deux fois", function () {
    $this->artisan('erp:creer-admin')
        ->expectsQuestion('Nom et prénom', 'Paul Biya')
        ->expectsQuestion('Adresse e-mail (sert à se connecter)', 'paul@exemple.cm')
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'un-mot-de-passe-solide')
        ->expectsQuestion('Retapez le mot de passe', 'un-mot-de-passe-solide')
        ->assertSuccessful();

    expect(User::where('email', 'paul@exemple.cm')->exists())->toBeTrue();

    $this->artisan('erp:creer-admin')
        ->expectsQuestion('Nom et prénom', 'Q')
        ->expectsQuestion('Adresse e-mail (sert à se connecter)', 'q@exemple.cm')
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'un-mot-de-passe-solide')
        ->expectsQuestion('Retapez le mot de passe', 'autre-chose-autre-chose')
        ->expectsOutputToContain('différents')
        ->assertFailed();
});

test("--reinitialiser change le mot de passe d'un compte existant, et refuse un compte inconnu", function () {
    $u = User::create(['name' => 'X', 'email' => 'x@exemple.cm', 'password' => 'ancien-mot-de-passe', 'role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->artisan('erp:creer-admin', ['--reinitialiser' => true, '--email' => 'x@exemple.cm', '--mot-de-passe' => 'nouveau-mot-de-passe'])
        ->expectsOutputToContain('Mot de passe changé')->assertSuccessful();
    expect(Hash::check('nouveau-mot-de-passe', $u->fresh()->password))->toBeTrue()
        ->and(User::count())->toBe(1);

    $this->artisan('erp:creer-admin', ['--reinitialiser' => true, '--email' => 'inconnu@exemple.cm', '--mot-de-passe' => 'nouveau-mot-de-passe'])
        ->expectsOutputToContain('Aucun compte')->assertFailed();
});
