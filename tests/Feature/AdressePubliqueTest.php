<?php

/**
 * Adresse publique du serveur.
 *
 * Les ports des établissements sont publiés sur l'hôte : sur un serveur distant,
 * « localhost » est le poste de l'administrateur, pas le serveur. Liens « Ouvrir »,
 * APP_URL et ORIGIN des établissements et URL du mode assistance suivent donc
 * TENANTS_PUBLIC_HOST — « localhost » par défaut, pour ne rien changer en local.
 */

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function composeAdressePublique(Tenant $tenant, bool $avecSite = false): string
{
    $base = sys_get_temp_dir() . '/adresse-test-' . random_int(1, 999999);
    config(['provisioning.tenants_base_path' => $base]);

    $chemin = (new ReflectionMethod(TenantProvisioningService::class, 'generateDockerCompose'))
        ->invoke(app(TenantProvisioningService::class), $tenant, 'ghcr.io/x/app@sha256:aaa', $avecSite ? 'ghcr.io/x/web@sha256:bbb' : null, null, fn () => null);

    $yaml = file_get_contents($chemin);
    exec('rm -rf ' . escapeshellarg($base));

    return $yaml;
}

test('sans réglage, les adresses restent sur localhost', function () {
    expect(PublicUrl::host())->toBe('localhost')
        ->and(PublicUrl::forPort(8081))->toBe('http://localhost:8081')
        ->and(PublicUrl::forPort(null))->toBeNull();
});

test("un serveur distant donne son nom aux liens et aux URL des établissements", function () {
    config(['provisioning.public_host' => 'erp.exemple.cm']);
    $tenant = etablissementValide(['slug' => 'hotel-a', 'app_port' => 8081, 'web_port' => 9081, 'modules' => ['website', 'api']]);

    expect($tenant->appUrl())->toBe('http://erp.exemple.cm:8081')
        ->and($tenant->websiteUrl())->toBe('http://erp.exemple.cm:9081');

    $yaml = composeAdressePublique($tenant, avecSite: true);
    expect($yaml)->toContain('APP_URL: "http://erp.exemple.cm:8081"')
        ->and($yaml)->toContain('ORIGIN: "http://erp.exemple.cm:9081"')
        ->and($yaml)->not->toContain('localhost:8081');
});

test("un réglage vide retombe sur localhost plutôt que de produire une adresse cassée", function () {
    config(['provisioning.public_host' => '  ']);

    expect(PublicUrl::origin())->toBe('http://localhost');
});
