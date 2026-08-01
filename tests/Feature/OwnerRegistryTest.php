<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Registre des propriétaires : liste, fiche, et les quatre actions du menu
 * contextuel. Le point sensible est la suppression — la clé étrangère
 * tenants.owner_id est en cascade, donc supprimer un propriétaire qui détient
 * encore des établissements les effacerait de la base sans toucher à leurs
 * conteneurs ni à leurs sauvegardes.
 */

function techAdmin(): User
{
    return User::factory()->create([
        'role'      => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);
}

function proprietaire(array $attributs = []): User
{
    return User::factory()->create(array_merge([
        'name'         => 'Aminatou Njoya',
        'email'        => 'aminatou' . random_int(1, 99999) . '@example.com',
        'role'         => User::ROLE_OWNER,
        'is_active'    => true,
        'phone'        => '+237699112233',
        'company_name' => 'Njoya Hospitality',
        'nationality'  => 'Camerounaise',
    ], $attributs));
}

function etablissement(User $owner, string $nom = 'Villa Boutanga'): Tenant
{
    return Tenant::create([
        'name'      => $nom,
        'slug'      => \Illuminate\Support\Str::slug($nom) . '-' . random_int(1, 99999),
        'db_name'   => 'db_' . random_int(1000, 99999),
        'owner_id'  => $owner->id,
        'is_active' => true,
    ]);
}

// ── Accès ─────────────────────────────────────────────────────────────────────

test('la page des propriétaires est réservée à l\'administrateur technique', function () {
    $this->get(route('tech.owners.index'))->assertRedirect(route('login'));

    // Un propriétaire connecté ne doit pas voir le registre de ses confrères.
    // La plateforme refuse par redirection avec un message, pas par un 403.
    $this->actingAs(proprietaire())
        ->get(route('tech.owners.index'))
        ->assertRedirect()
        ->assertSessionHas('access_denied_popup');
});

test('un compte qui n\'est pas propriétaire ne s\'ouvre pas depuis cet écran', function () {
    $autreAdmin = techAdmin();

    // Sans ce garde-fou, l'écran servirait à modifier ou supprimer un
    // administrateur technique via une simple URL.
    $this->actingAs(techAdmin())
        ->get(route('tech.owners.show', $autreAdmin))
        ->assertNotFound();
});

// ── Liste ─────────────────────────────────────────────────────────────────────

test('la liste affiche tous les propriétaires avec leur nombre d\'établissements', function () {
    $admin = techAdmin();
    $avec  = proprietaire(['name' => 'Aminatou Njoya']);
    $sans  = proprietaire(['name' => 'Serge Mbarga']);
    etablissement($avec, 'Villa Boutanga');
    etablissement($avec, 'Résidence Kribi');

    $reponse = $this->actingAs($admin)->get(route('tech.owners.index'))->assertOk();

    $reponse->assertSee('Aminatou Njoya')
        ->assertSee('Serge Mbarga')
        ->assertSee('Njoya Hospitality');

    $liste = $reponse->viewData('owners');
    expect($liste->firstWhere('id', $avec->id)->tenants_count)->toBe(2)
        ->and($liste->firstWhere('id', $sans->id)->tenants_count)->toBe(0);
});

test('la recherche filtre par nom, email ou société', function () {
    $admin = techAdmin();
    proprietaire(['name' => 'Aminatou Njoya', 'company_name' => 'Njoya Hospitality']);
    proprietaire(['name' => 'Serge Mbarga', 'company_name' => 'Mbarga Groupe']);

    $resultats = $this->actingAs($admin)
        ->get(route('tech.owners.index', ['q' => 'Njoya']))
        ->assertOk()
        ->viewData('owners');

    expect($resultats)->toHaveCount(1)
        ->and($resultats->first()->name)->toBe('Aminatou Njoya');
});

// ── Fiche et accès aux établissements ─────────────────────────────────────────

test('la fiche donne accès direct à chaque établissement du propriétaire', function () {
    $admin = techAdmin();
    $owner = proprietaire();
    $villa = etablissement($owner, 'Villa Boutanga');

    // Un autre propriétaire, pour vérifier le cloisonnement.
    $autre = etablissement(proprietaire(), 'Hôtel Voisin');

    $this->actingAs($admin)
        ->get(route('tech.owners.show', $owner))
        ->assertOk()
        ->assertSee('Villa Boutanga')
        ->assertDontSee('Hôtel Voisin')
        // C'est tout l'intérêt de l'écran : ouvrir la gestion d'un
        // établissement sans repasser par l'onglet Établissements.
        ->assertSee(route('tech.establishments.show', $villa), false);
});

// ── Actions du menu contextuel ────────────────────────────────────────────────

test('modifier enregistre les coordonnées sans toucher au mot de passe', function () {
    $admin = techAdmin();
    $owner = proprietaire();
    $motDePasseInitial = $owner->password;

    $this->actingAs($admin)
        ->post(route('tech.owners.update', $owner), [
            'name'         => 'Aminatou Njoya-Fotso',
            'email'        => 'nouvelle.adresse@example.com',
            'phone'        => '+237677000000',
            'company_name' => 'Njoya Group',
            'nationality'  => 'Camerounaise',
            'password'     => '',   // laissé vide
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $owner->refresh();

    expect($owner->name)->toBe('Aminatou Njoya-Fotso')
        ->and($owner->email)->toBe('nouvelle.adresse@example.com')
        ->and($owner->company_name)->toBe('Njoya Group')
        // Un champ mot de passe vide signifie « ne pas y toucher », pas « effacer ».
        ->and($owner->password)->toBe($motDePasseInitial);
});

test('modifier change le mot de passe quand il est renseigné', function () {
    $admin = techAdmin();
    $owner = proprietaire();

    $this->actingAs($admin)->post(route('tech.owners.update', $owner), [
        'name'     => $owner->name,
        'email'    => $owner->email,
        'password' => 'nouveau-secret',
    ])->assertRedirect();

    expect(Hash::check('nouveau-secret', $owner->fresh()->password))->toBeTrue();
});

test('modifier refuse une adresse déjà prise par un autre compte', function () {
    $admin = techAdmin();
    $owner = proprietaire();
    $autre = proprietaire(['email' => 'occupe@example.com']);

    $this->actingAs($admin)
        ->post(route('tech.owners.update', $owner), [
            'name'  => $owner->name,
            'email' => 'occupe@example.com',
        ])
        ->assertSessionHasErrors('email');

    expect($owner->fresh()->email)->not->toBe('occupe@example.com');
});

test('désactiver puis activer bascule le compte', function () {
    $admin = techAdmin();
    $owner = proprietaire(['is_active' => true]);

    $this->actingAs($admin)->post(route('tech.owners.toggle-active', $owner))->assertRedirect();
    expect($owner->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post(route('tech.owners.toggle-active', $owner))->assertRedirect();
    expect($owner->fresh()->is_active)->toBeTrue();
});

test('supprimer un propriétaire sans établissement retire le compte', function () {
    $admin = techAdmin();
    $owner = proprietaire();

    $this->actingAs($admin)
        ->delete(route('tech.owners.destroy', $owner))
        ->assertRedirect(route('tech.owners.index'))
        ->assertSessionHas('success');

    expect(User::find($owner->id))->toBeNull();
});

test('supprimer est refusé tant que le propriétaire détient des établissements', function () {
    $admin = techAdmin();
    $owner = proprietaire();
    $villa = etablissement($owner);

    $this->actingAs($admin)
        ->delete(route('tech.owners.destroy', $owner))
        ->assertRedirect()
        ->assertSessionHas('error');

    // La clé étrangère est en cascade : sans ce refus, l'établissement
    // disparaîtrait de la base alors que ses conteneurs et sa base de
    // données continueraient de tourner, orphelins.
    expect(User::find($owner->id))->not->toBeNull()
        ->and(Tenant::find($villa->id))->not->toBeNull();
});

test('les actions sont fermées à un propriétaire connecté', function () {
    $cible = proprietaire();

    $this->actingAs(proprietaire())
        ->post(route('tech.owners.toggle-active', $cible))
        ->assertRedirect()
        ->assertSessionHas('access_denied_popup');

    // Le refus doit être effectif, pas seulement affiché.
    expect($cible->fresh()->is_active)->toBeTrue();
});

// ── Navigation ────────────────────────────────────────────────────────────────

test('le tableau de bord expose un lien vers les propriétaires', function () {
    $this->actingAs(techAdmin())
        ->get(route('tech.dashboard'))
        ->assertOk()
        ->assertSee(route('tech.owners.index'), false)
        ->assertSee('Proprietaires');
});

test('l\'onglet owners du tableau de bord retombe sur la supervision', function () {
    // « owners » est un lien vers une page à part, pas un onglet : demander
    // ?tab=owners ne doit pas casser le rendu faute de titre.
    $this->actingAs(techAdmin())
        ->get(route('tech.dashboard', ['tab' => 'owners']))
        ->assertOk();
});
