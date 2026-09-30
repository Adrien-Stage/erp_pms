<?php

/**
 * Chaque établissement reçoit ses propres secrets de service.
 *
 * Une seule valeur de REPORTING_SECRET et d'ASSISTANCE_SECRET était injectée
 * dans tous les établissements et tous les GRC : lire l'environnement d'un
 * seul conteneur suffisait pour lire les finances, réécrire la matrice des
 * droits et ouvrir une session d'administration chez tous les autres.
 */

use App\Models\AssistanceSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessReportingClient;
use App\Services\PermissionMatrixClient;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const SECRET_COMMUN_REPORTING  = 'secret-commun-de-reporting';
const SECRET_COMMUN_ASSISTANCE = 'secret-commun-d-assistance';

beforeEach(function () {
    $this->dossierComposes = sys_get_temp_dir() . '/secrets-test-' . random_int(1, 999999);

    config([
        'provisioning.tenants_base_path'  => $this->dossierComposes,
        'provisioning.registry_image_grc' => 'ghcr.io/clyde237/wetchah_grc',
        'provisioning.reporting_secret'   => SECRET_COMMUN_REPORTING,
        'assistance.secret'               => SECRET_COMMUN_ASSISTANCE,
    ]);
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->dossierComposes));
});

/** Génère (ou régénère) le compose de l'établissement et rend son YAML. */
function composeDe(Tenant $tenant): string
{
    $chemin = (new ReflectionMethod(TenantProvisioningService::class, 'generateDockerCompose'))
        ->invoke(app(TenantProvisioningService::class), $tenant, 'ghcr.io/x/app@sha256:aaa', null, null, fn () => null);

    return file_get_contents($chemin);
}

/** Toutes les valeurs d'une variable dans le YAML, dans l'ordre des services. */
function valeursDe(string $yaml, string $cle): array
{
    preg_match_all('/^\s*' . preg_quote($cle, '/') . ':\s*"([^"]*)"/m', $yaml, $m);

    return $m[1];
}

function etablissementAvecGrc(string $slug): Tenant
{
    return etablissementValide([
        'slug'           => $slug,
        'app_port'       => 8100 + random_int(1, 800),
        'modules'        => ['grc'],
        'grc_image_tag'  => 'sha256:' . str_repeat('b', 64),
        'provisioned_at' => now(),
    ]);
}

test('deux établissements ne partagent aucun secret de service', function () {
    $zingana = composeDe(etablissementAvecGrc('zingana'));
    $koreTeck = composeDe(etablissementAvecGrc('kore-teck'));

    foreach (['REPORTING_SECRET', 'ASSISTANCE_SECRET'] as $cle) {
        $a = valeursDe($zingana, $cle)[0];
        $b = valeursDe($koreTeck, $cle)[0];

        expect($a)->toMatch('/^[0-9a-f]{64}$/')
            ->and($b)->toMatch('/^[0-9a-f]{64}$/')
            ->and($a)->not->toBe($b)
            // Le secret commun de la console n'est plus distribué.
            ->and($a)->not->toBe(SECRET_COMMUN_REPORTING)
            ->and($a)->not->toBe(SECRET_COMMUN_ASSISTANCE);
    }
});

test("l'application et le GRC d'un même établissement partagent son secret", function () {
    $yaml = composeDe(etablissementAvecGrc('zingana'));

    $secrets = valeursDe($yaml, 'REPORTING_SECRET');

    // Un pour le conteneur app, un pour le GRC qui interroge cette même app.
    expect($secrets)->toHaveCount(2)
        ->and($secrets[0])->toBe($secrets[1]);
});

test('une mise à jour reprend les secrets déjà figés', function () {
    $tenant = etablissementAvecGrc('zingana');

    $premier = composeDe($tenant);
    $second  = composeDe($tenant);

    // En changer à chaque mise à jour couperait la console le temps du redémarrage.
    foreach (['REPORTING_SECRET', 'ASSISTANCE_SECRET'] as $cle) {
        expect(valeursDe($second, $cle))->toBe(valeursDe($premier, $cle));
    }
});

test('un établissement encore sur le secret commun en change à la génération suivante', function () {
    $tenant = etablissementAvecGrc('zingana');

    // Compose hérité : les deux secrets communs y sont figés.
    @mkdir(dirname($tenant->composePath()), 0777, true);
    file_put_contents($tenant->composePath(), implode("\n", [
        'services:',
        '  meka-erp-zingana-app:',
        '    environment:',
        '      ASSISTANCE_SECRET: "' . SECRET_COMMUN_ASSISTANCE . '"',
        '      REPORTING_SECRET: "' . SECRET_COMMUN_REPORTING . '"',
    ]) . "\n");

    $yaml = composeDe($tenant);

    expect(valeursDe($yaml, 'REPORTING_SECRET')[0])->not->toBe(SECRET_COMMUN_REPORTING)
        ->and(valeursDe($yaml, 'ASSISTANCE_SECRET')[0])->not->toBe(SECRET_COMMUN_ASSISTANCE);
});

test("la console s'adresse à chaque établissement avec son propre secret", function () {
    $tenant = etablissementAvecGrc('zingana');
    $secret = valeursDe(composeDe($tenant), 'REPORTING_SECRET')[0];

    Http::fake(['*' => Http::response(['catalogue' => [], 'ecarts' => []], 200)]);

    app(PermissionMatrixClient::class)->fetch($tenant);
    app(BusinessReportingClient::class)->fetch($tenant, 'summary');

    Http::assertSentCount(2);
    Http::assertSent(fn ($requete) => $requete->hasHeader('Authorization', 'Bearer ' . $secret));
    Http::assertNotSent(fn ($requete) => $requete->hasHeader('Authorization', 'Bearer ' . SECRET_COMMUN_REPORTING));
});

test("sans compose, la console retombe sur le secret commun, le seul qu'il ait pu recevoir", function () {
    $tenant = etablissementAvecGrc('ancien');   // aucun compose généré

    Http::fake(['*' => Http::response(['catalogue' => [], 'ecarts' => []], 200)]);

    app(PermissionMatrixClient::class)->fetch($tenant);

    Http::assertSent(fn ($requete) => $requete->hasHeader('Authorization', 'Bearer ' . SECRET_COMMUN_REPORTING));
});

test("le jeton d'assistance est signé avec le secret de l'établissement", function () {
    $tenant = etablissementAvecGrc('zingana');
    $secret = valeursDe(composeDe($tenant), 'ASSISTANCE_SECRET')[0];

    $session = AssistanceSession::create([
        'tenant_id'  => $tenant->id,
        'user_id'    => User::factory()->create(['role' => User::ROLE_TECH_ADMIN])->id,
        'reason'     => 'Vérification de la signature',
        'token'      => str_repeat('t', 48),
        'status'     => 'active',
        'expires_at' => now()->addMinutes(30),
    ]);

    parse_str((string) parse_url($session->entryUrl(), PHP_URL_QUERY), $requete);
    [$charge, $signature] = explode('.', $requete['token']);

    // L'application de cet établissement le vérifie avec son propre secret ;
    // signé avec le secret commun, il n'ouvrirait plus rien.
    expect(hash_equals(hash_hmac('sha256', $charge, $secret), $signature))->toBeTrue()
        ->and(hash_equals(hash_hmac('sha256', $charge, SECRET_COMMUN_ASSISTANCE), $signature))->toBeFalse();
});
