<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Éditeur de contenu : rôle volontairement étroit.
 *
 * Ces tests portent surtout sur ce que l'éditeur ne peut PAS faire. L'adresse
 * distincte de son espace ne protège rien par elle-même — c'est le refus de
 * connexion des autres rôles et le cloisonnement par tenant_id qui protègent,
 * et c'est donc cela qu'il faut verrouiller.
 */

function siteTenant(string $nom = 'Villa Boutanga'): Tenant
{
    return Tenant::create([
        'name'     => $nom,
        'slug'     => \Illuminate\Support\Str::slug($nom) . '-' . random_int(1, 99999),
        'db_name'  => 'db_' . random_int(1000, 99999),
        'owner_id' => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'is_active' => true,
        'modules'  => ['website'],
    ]);
}

function editeur(Tenant $tenant, array $attributs = []): User
{
    return User::factory()->create(array_merge([
        'name'      => 'Estelle Ngo Bakoa',
        'role'      => User::ROLE_SITE_EDITOR,
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'password'  => Hash::make('secret-editeur'),
    ], $attributs));
}

// ── Connexion ─────────────────────────────────────────────────────────────────

test('la page de connexion ne révèle pas qu\'il s\'agit de l\'ERP', function () {
    $page = $this->get(route('site-editor.login'))->assertOk()->getContent();

    // L'éditeur n'a pas à deviner qu'une console d'administration partage
    // l'application. C'est de la discrétion, pas de la sécurité — mais c'est
    // ce qui a été demandé, et une fuite ici la rendrait inutile.
    foreach (['WeTchah', 'ERP', 'Administration', 'admin'] as $terme) {
        expect(stripos($page, $terme))->toBeFalse("Le terme « {$terme} » apparaît sur la page de connexion");
    }

    expect($page)->toContain('Espace éditeur');
});

test('un éditeur actif et rattaché accède à son espace', function () {
    $tenant = siteTenant();
    $editeur = editeur($tenant);

    $this->post(route('site-editor.login.store'), [
        'email'    => $editeur->email,
        'password' => 'secret-editeur',
    ])->assertRedirect(route('site-editor.content'));

    expect(auth()->id())->toBe($editeur->id);
});

test('un administrateur ne peut pas passer par la porte de l\'éditeur', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN, 'is_active' => true, 'password' => Hash::make('secret-admin'),
    ]);

    $this->post(route('site-editor.login.store'), [
        'email' => $admin->email, 'password' => 'secret-admin',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

test('un compte désactivé est refusé', function () {
    $editeur = editeur(siteTenant(), ['is_active' => false]);

    $this->post(route('site-editor.login.store'), [
        'email' => $editeur->email, 'password' => 'secret-editeur',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

test('le message d\'échec ne distingue pas les causes', function () {
    $editeur = editeur(siteTenant());

    // Identifiant inconnu et mauvais mot de passe doivent donner le même
    // message : sinon on peut énumérer les comptes existants.
    $inconnu = $this->post(route('site-editor.login.store'), [
        'email' => 'personne@example.com', 'password' => 'peu-importe',
    ])->assertSessionHasErrors('email');

    $mauvais = $this->post(route('site-editor.login.store'), [
        'email' => $editeur->email, 'password' => 'mauvais',
    ])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe('Identifiants incorrects.');
});

test('un éditeur sans établissement rattaché ne peut pas entrer', function () {
    $orphelin = User::factory()->create([
        'role' => User::ROLE_SITE_EDITOR, 'tenant_id' => null,
        'is_active' => true, 'password' => Hash::make('secret-editeur'),
    ]);

    $this->post(route('site-editor.login.store'), [
        'email' => $orphelin->email, 'password' => 'secret-editeur',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

// ── Cloisonnement ─────────────────────────────────────────────────────────────

test('l\'éditeur ne voit que le contenu de son établissement', function () {
    $sien   = siteTenant('Villa Boutanga');
    $autre  = siteTenant('Hôtel Voisin');

    $this->actingAs(editeur($sien))
        ->get(route('site-editor.content'))
        ->assertOk()
        ->assertSee('Villa Boutanga')
        ->assertDontSee('Hôtel Voisin');
});

test('l\'éditeur ne peut pas modifier le contenu d\'un autre établissement', function () {
    $sien  = siteTenant('Villa Boutanga');
    $autre = siteTenant('Hôtel Voisin');
    $editeur = editeur($sien);

    // L'établissement vient du compte, jamais de la requête. Deux barrières se
    // succèdent : le middleware de rôle écarte l'éditeur des routes de l'ERP
    // (refus par redirection), et si elle venait à tomber, la comparaison sur
    // tenant_id dans updateSiteContent refuserait à son tour.
    $reponse = $this->actingAs($editeur)
        ->post(route('tech.establishments.site-content', $autre), ['seo_title' => 'Détourné']);

    expect($reponse->status())->not->toBe(200)
        ->and($autre->fresh()->site_content['seo']['title'] ?? null)->not->toBe('Détourné');

    // La seconde barrière, éprouvée directement : même appelée hors middleware,
    // la méthode refuse un établissement qui n'est pas celui de l'éditeur.
    $this->actingAs($editeur);
    $refus = null;
    try {
        app(\App\Http\Controllers\AdminAuditController::class)
            ->updateSiteContent(request()->merge(['seo_title' => 'Détourné']), $autre);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $refus = $e->getStatusCode();
    }

    expect($refus)->toBe(403);
});

test('l\'éditeur est tenu à l\'écart du reste de l\'ERP', function () {
    $editeur = editeur(siteTenant());
    $this->actingAs($editeur);

    // Aucune de ces pages ne doit s'ouvrir : ni la supervision, ni le registre
    // des propriétaires, ni la fiche d'un établissement.
    foreach ([
        route('tech.dashboard'),
        route('tech.owners.index'),
        route('tech.establishments.index'),
    ] as $url) {
        $reponse = $this->get($url);
        expect($reponse->status())->not->toBe(200, "L'éditeur a pu ouvrir {$url}");
    }
});

test('un visiteur non connecté n\'atteint pas l\'espace éditeur', function () {
    $this->get(route('site-editor.content'))->assertRedirect();
});

// ── Édition ───────────────────────────────────────────────────────────────────

test('l\'éditeur enregistre le contenu de son site', function () {
    $tenant = siteTenant();

    $this->actingAs(editeur($tenant))
        ->post(route('site-editor.content.update'), [
            'seo_title'       => 'Villa Boutanga — Séjour de charme',
            'seo_description' => 'Un écrin de verdure au bord du lac.',
        ])
        ->assertRedirect(route('site-editor.content'));

    $contenu = $tenant->fresh()->site_content;

    expect($contenu['seo']['title'])->toBe('Villa Boutanga — Séjour de charme')
        ->and($contenu['seo']['description'])->toBe('Un écrin de verdure au bord du lac.');
});

test('le formulaire de l\'éditeur est celui de l\'ERP', function () {
    $tenant = siteTenant();

    // Le partial est partagé : un champ ajouté côté ERP doit apparaître ici
    // sans travail supplémentaire, au lieu que les deux écrans divergent.
    $this->actingAs(editeur($tenant))
        ->get(route('site-editor.content'))
        ->assertOk()
        ->assertSee(route('site-editor.content.update'), false)
        ->assertSee('Identité du site');
});

// ── Administration des comptes éditeurs ───────────────────────────────────────

test('l\'administrateur crée un éditeur rattaché à l\'établissement', function () {
    $tenant = siteTenant();
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('tech.establishments.editors.store', $tenant), [
            'name' => 'Estelle Ngo Bakoa', 'email' => 'estelle@example.com', 'password' => 'motdepasse',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $cree = User::where('email', 'estelle@example.com')->first();

    expect($cree)->not->toBeNull()
        ->and($cree->role)->toBe(User::ROLE_SITE_EDITOR)
        ->and($cree->tenant_id)->toBe($tenant->id)
        ->and($cree->is_active)->toBeTrue();
});

test('on ne peut pas agir sur l\'éditeur d\'un autre établissement', function () {
    $sien  = siteTenant('Villa Boutanga');
    $autre = siteTenant('Hôtel Voisin');
    $editeurAutre = editeur($autre);
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    // Croiser tenant et éditeur dans l'URL ne doit rien permettre.
    $this->actingAs($admin)
        ->post(route('tech.establishments.editors.toggle', ['tenant' => $sien, 'editor' => $editeurAutre]))
        ->assertNotFound();

    expect($editeurAutre->fresh()->is_active)->toBeTrue();
});

test('la route des éditeurs ne permet pas de toucher un administrateur', function () {
    $tenant = siteTenant();
    $admin  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
    $cible  = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('tech.establishments.editors.destroy', ['tenant' => $tenant, 'editor' => $cible]))
        ->assertNotFound();

    expect(User::find($cible->id))->not->toBeNull();
});

test("l'espace éditeur ne porte pas non plus la marque de l'ERP", function () {
    // Le logo et le favicon nomment le produit dans leur chemin même
    // (images/logo-erp-mark.png) : les poser ici annulerait la discrétion que
    // le test précédent protège.
    $page = $this->get(route('site-editor.login'))->assertOk()->getContent();

    expect($page)->not->toContain('logo-erp')
        ->and($page)->not->toContain('apple-touch-icon');
});
