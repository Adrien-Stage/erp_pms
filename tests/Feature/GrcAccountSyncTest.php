<?php

/**
 * Report d'un compte de contrôleur vers le conteneur GRC de l'établissement.
 *
 * Le GRC tient sa propre base : un compte créé ou modifié dans le PMS n'y
 * existe pas tant qu'il n'y a pas été poussé. Deux défauts l'empêchaient — la
 * création visait 127.0.0.1, adresse qui depuis ce conteneur désigne l'ERP
 * lui-même, et le changement de mot de passe ne poussait rien du tout.
 */

use App\Models\Tenant;
use App\Services\GrcAccountSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function tenantGrc(array $attributs = []): Tenant
{
    return etablissementValide(array_merge([
        'slug'                 => 'villa-grc',
        'modules'              => ['grc'],
        'docker_grc_container' => 'meka-erp-villa-grc-grc',
    ], $attributs));
}

function compteControleur(array $attributs = []): array
{
    return array_merge([
        'email'     => 'controleur@exemple.cm',
        'password'  => 'motdepasse',
        'full_name' => 'Paul Atangana',
    ], $attributs);
}

test('le compte est poussé au conteneur, pas à 127.0.0.1', function () {
    config(['provisioning.reporting_secret' => 'secret-de-service']);
    Http::fake(['*' => Http::response(['id' => 1], 201)]);

    $reporte = app(GrcAccountSync::class)->push(tenantGrc(), compteControleur());

    expect($reporte)->toBeTrue();

    Http::assertSent(function ($requete) {
        // L'adresse du conteneur sur le réseau Docker, et son port interne —
        // 127.0.0.1 désignerait l'ERP, le port publié n'existe que sur l'hôte.
        return $requete->url() === 'http://meka-erp-villa-grc-grc:8000/api/v1/users/provision-from-erp'
            && $requete->hasHeader('Authorization', 'Bearer secret-de-service')
            && $requete['role'] === 'controller'
            && $requete['email'] === 'controleur@exemple.cm';
    });
});

test('un établissement sans module GRC ne déclenche aucun appel', function () {
    config(['provisioning.reporting_secret' => 'secret-de-service']);
    Http::fake();

    $reporte = app(GrcAccountSync::class)->push(tenantGrc(['modules' => []]), compteControleur());

    // null, et non false : il n'y a rien à reporter, ce n'est pas un échec.
    expect($reporte)->toBeNull();
    Http::assertNothingSent();
});

test('sans secret de service, aucun appel et un échec franc', function () {
    config(['provisioning.reporting_secret' => '']);
    Http::fake();

    expect(app(GrcAccountSync::class)->push(tenantGrc(), compteControleur()))->toBeFalse();
    Http::assertNothingSent();
});

test('un refus du GRC est rapporté comme un échec', function () {
    config(['provisioning.reporting_secret' => 'secret-de-service']);
    Http::fake(['*' => Http::response(['detail' => 'Secret invalide'], 403)]);

    expect(app(GrcAccountSync::class)->push(tenantGrc(), compteControleur()))->toBeFalse();
});

test('un conteneur injoignable est rapporté comme un échec, sans exception', function () {
    config(['provisioning.reporting_secret' => 'secret-de-service']);
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connexion refusée'));

    expect(app(GrcAccountSync::class)->push(tenantGrc(), compteControleur()))->toBeFalse();
});

test("l'ancienne adresse est transmise pour un renommage", function () {
    config(['provisioning.reporting_secret' => 'secret-de-service']);
    Http::fake(['*' => Http::response(['id' => 1], 201)]);

    app(GrcAccountSync::class)->push(tenantGrc(), compteControleur([
        'email'          => 'nouvelle@exemple.cm',
        'previous_email' => 'ancienne@exemple.cm',
    ]));

    // Sans elle, le GRC créerait un second compte et l'ancienne adresse
    // continuerait d'ouvrir le portail.
    Http::assertSent(fn ($r) => $r['previous_email'] === 'ancienne@exemple.cm');
});

test('le message rendu dit franchement si le report a échoué', function () {
    $sync = app(GrcAccountSync::class);

    expect($sync->message(true))->toContain('reporté dans le module GRC')
        ->and($sync->message(false))->toContain("n'ouvre pas encore le portail")
        ->and($sync->message(null))->toBe('');
});
