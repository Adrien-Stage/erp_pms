<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Lot 0 : Fermer les accès indus (ERP)
| S1, S2, S4, S10
|--------------------------------------------------------------------------
*/

test('S1 : un compte désactivé ayant une session ouverte est immédiatement éconduit', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    // Session ouverte et active : accès autorisé
    $this->actingAs($user)->get(route('tech.owners.index'))->assertOk();

    // Désactivation du compte
    $user->update(['is_active' => false]);

    // Toute nouvelle requête protégée doit être immédiatement refusée et déconnecter la session
    $reponse = $this->actingAs($user)->get(route('tech.owners.index'));
    $reponse->assertRedirect(route('login'));
    $reponse->assertSessionHasErrors('login');

    expect(Auth::check())->toBeFalse();
});

test('S1 : une écriture est également refusée pour un utilisateur désactivé', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => false,
    ]);

    $reponse = $this->actingAs($user)->post(route('tech.establishments.store'), [
        'name' => 'Établissement Pirate',
        'subdomain' => 'pirate',
    ]);

    $reponse->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

test('S1 : toggleUserActive révoque le remember_token et les sessions en base', function () {
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
    $target = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true, 'remember_token' => 'ancien-token']);

    DB::table('sessions')->insert([
        'id' => 'session-test-id',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestBrowser',
        'payload' => 'dummy',
        'last_activity' => time(),
    ]);

    config(['session.driver' => 'database']);

    $this->actingAs($admin)->post(route('tech.users.toggle-active', $target))->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->is_active)->toBeFalse()
        ->and($target->remember_token)->not->toBe('ancien-token');

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
});

test('S2 : la connexion ERP bloque après 5 échecs consécutifs', function () {
    RateLimiter::clear(Str::transliterate(Str::lower('audit@example.test').'|127.0.0.1'));

    for ($i = 0; $i < 5; $i++) {
        $reponse = $this->post('/login', [
            'login' => 'audit@example.test',
            'password' => 'mauvais-mdp',
        ]);
        $reponse->assertSessionHasErrors('login');
    }

    // Le 6ème essai doit être bloqué par le limiteur de débit
    $bloque = $this->post('/login', [
        'login' => 'audit@example.test',
        'password' => 'mauvais-mdp',
    ]);

    $bloque->assertSessionHasErrors('login');
    $message = session('errors')->first('login');
    expect($message)->toMatch('/trop de tentatives|secondes|seconds|too many/i');
});

test('S4 : le modèle .env.docker ne contient aucune clé de chiffrement et installation.md mentionne key:generate', function () {
    $envDocker = file_get_contents(base_path('.env.docker'));
    expect($envDocker)->toMatch('/^APP_KEY=\s*$/m');

    $doc = file_get_contents(base_path('docs/installation.md'));
    expect($doc)->toContain('php artisan key:generate');
});

test('S10 : forcePasswordReset impose le changement de mot de passe et révoque les sessions', function () {
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_OWNER, 'remember_token' => 'token-avant']);

    DB::table('sessions')->insert([
        'id' => 'session-target',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestBrowser',
        'payload' => 'dummy',
        'last_activity' => time(),
    ]);

    config(['session.driver' => 'database']);

    $this->actingAs($admin)->post(route('tech.users.reset-password', $target))->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->must_change_password)->toBeTrue()
        ->and($target->remember_token)->not->toBe('token-avant');

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
});

test('S10 : la création d\'un éditeur de site impose must_change_password', function () {
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN]);
    $tenant = etablissementValide(['name' => 'Hôtel Test', 'slug' => 'hotel-test']);

    $this->actingAs($admin)->post(route('tech.establishments.editors.store', $tenant), [
        'name' => 'Éditeur Test',
        'email' => 'editeur@example.test',
        'password' => 'secret12345',
    ])->assertSessionHasNoErrors();

    $editeur = User::where('email', 'editeur@example.test')->sole();
    expect($editeur->must_change_password)->toBeTrue();
});
