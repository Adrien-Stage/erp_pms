<?php

/**
 * Assemblage des références d'image dans le docker-compose généré.
 *
 * Une mise à jour applicative laisse volontairement le GRC sur sa version
 * déjà figée : le compose retombe alors sur grc_image_tag, qui contient un
 * digest. Ce seul endroit le rattachait par « : » au lieu de « @ », d'où
 * « ghcr.io/…/wetchah_grc:sha256:… » et le refus de Docker au « compose up ».
 *
 * Le pull, lui, réussissait — il empruntait l'autre chemin, correct. Le
 * journal annonçait donc « Image prête » juste avant l'échec.
 */

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const DIGEST_GRC = 'sha256:bdb83556282e6326a17f8db90730f700b4cb714ce1048f79b4c73ca92e736447';

/** Génère le compose et rend son YAML. */
function composeGenere(Tenant $tenant, ?string $grcImageRef = null): string
{
    $base = sys_get_temp_dir() . '/compose-test-' . random_int(1, 999999);
    config([
        'provisioning.tenants_base_path'  => $base,
        'provisioning.registry_image_grc' => 'ghcr.io/clyde237/wetchah_grc',
    ]);

    $chemin = (new ReflectionMethod(TenantProvisioningService::class, 'generateDockerCompose'))
        ->invoke(app(TenantProvisioningService::class), $tenant, 'ghcr.io/x/app@sha256:aaa', null, $grcImageRef, fn () => null);

    $yaml = file_get_contents($chemin);
    exec('rm -rf ' . escapeshellarg($base));

    return $yaml;
}

function tenantAvecGrc(array $attributs = []): Tenant
{
    return etablissementValide(array_merge([
        'slug'           => 'zingana',
        'modules'        => ['grc'],
        'grc_image_tag'  => DIGEST_GRC,
    ], $attributs));
}

test('un digest figé est rattaché par @, jamais par :', function () {
    $yaml = composeGenere(tenantAvecGrc());

    expect($yaml)->toContain('ghcr.io/clyde237/wetchah_grc@' . DIGEST_GRC)
        // La forme exacte que Docker rejetait.
        ->and($yaml)->not->toContain('wetchah_grc:sha256:');
});

test('un GRC jamais figé retombe sur le tag latest, rattaché par :', function () {
    $yaml = composeGenere(tenantAvecGrc(['grc_image_tag' => null]));

    expect($yaml)->toContain('ghcr.io/clyde237/wetchah_grc:latest')
        ->and($yaml)->not->toContain('wetchah_grc@latest');   // un tag n'est pas un digest
});

test('une référence explicitement fournie est reprise telle quelle', function () {
    $fournie = 'ghcr.io/clyde237/wetchah_grc@sha256:' . str_repeat('c', 64);

    expect(composeGenere(tenantAvecGrc(), $fournie))->toContain($fournie);
});

test("l'établissement sans module GRC ne déclare aucun service GRC", function () {
    $yaml = composeGenere(etablissementValide(['slug' => 'sans-grc', 'modules' => []]));

    expect($yaml)->not->toContain('wetchah_grc');
});
